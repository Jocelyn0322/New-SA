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

// POST：強制刪除影片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_delete_video'])) {
    $vid  = (int)$_POST['video_id'];
    $row  = $pdo->prepare("SELECT file_path FROM videos WHERE id = ?");
    $row->execute([$vid]);
    $vrow = $row->fetch();
    if ($vrow) {
        $fp = __DIR__ . '/' . $vrow['file_path'];
        if (file_exists($fp) && is_file($fp)) unlink($fp);
        $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$vid]);
        $msg     = '已刪除影片';
        $msgType = 'success';
    }
    $tab = 'videos';
}

// POST：切換使用者角色
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_role'])) {
    $targetUser  = trim($_POST['target_user'] ?? '');
    $currentRole = trim($_POST['current_role'] ?? '');
    if ($targetUser && $targetUser !== $adminUser) {
        $newRole = ($currentRole === 'admin') ? 'user' : 'admin';
        $pdo->prepare("UPDATE users SET role = ? WHERE username = ?")
            ->execute([$newRole, $targetUser]);
        $msg     = "已將「{$targetUser}」的身份改為 {$newRole}";
        $msgType = 'success';
    }
    $tab = 'users';
}

// ─── 各 Tab 資料查詢 ───────────────────────────────────────

// 數據統計
$stats = [];
if ($tab === 'stats') {
    $stats['users']    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['verified'] = $pdo->query("SELECT COUNT(*) FROM users WHERE email_verified = 1")->fetchColumn();
    $stats['videos']   = $pdo->query("SELECT COUNT(*) FROM videos WHERE is_active = 1")->fetchColumn();
    try {
        $stats['comments'] = $pdo->query("SELECT COUNT(*) FROM video_comments")->fetchColumn();
        $stats['reports']  = $pdo->query("SELECT COUNT(*) FROM video_reports WHERE status = 'pending'")->fetchColumn();
    } catch (Exception $e) {
        $stats['comments'] = 0;
        $stats['reports']  = 0;
    }
    $recentVideos = $pdo->query("
        SELECT title, uploaded_by, upload_time, likes
        FROM videos WHERE is_active = 1
        ORDER BY upload_time DESC LIMIT 8
    ")->fetchAll();
}

// 影片管理
$allVideos = [];
if ($tab === 'videos') {
    $search = trim($_GET['q'] ?? '');
    if ($search !== '') {
        $s = $pdo->prepare("
            SELECT id, title, uploaded_by, upload_time, likes
            FROM videos WHERE is_active = 1
            AND (title LIKE ? OR uploaded_by LIKE ?)
            ORDER BY upload_time DESC
        ");
        $s->execute(["%{$search}%", "%{$search}%"]);
    } else {
        $s = $pdo->query("
            SELECT id, title, uploaded_by, upload_time, likes
            FROM videos WHERE is_active = 1
            ORDER BY upload_time DESC
        ");
    }
    $allVideos = $s->fetchAll();
}

// 會員管理
$allUsers = [];
if ($tab === 'users') {
    $allUsers = $pdo->query("
        SELECT username, email, role, email_verified
        FROM users
        ORDER BY role DESC, username ASC
    ")->fetchAll();
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

        /* ── Top bar ── */
        .adm-topbar {
            background: #1a1a2e;
            color: #fff;
            padding: 0 28px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .adm-topbar-logo { font-size: 18px; font-weight: 700; letter-spacing: 1px; }
        .adm-topbar-right { display: flex; align-items: center; gap: 16px; font-size: 13px; }
        .adm-topbar-right a {
            color: #ccc;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .adm-topbar-right a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .adm-topbar-right .logout-btn {
            background: rgba(220,53,69,0.2);
            color: #ff8a96;
            border-radius: 6px;
            padding: 6px 12px;
        }
        .adm-topbar-right .logout-btn:hover { background: rgba(220,53,69,0.4); color: #fff; }

        /* ── Tab nav ── */
        .adm-tabs {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 28px;
            display: flex;
            gap: 4px;
        }
        .adm-tab {
            display: inline-block;
            padding: 14px 20px;
            text-decoration: none;
            color: #666;
            font-size: 14px;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .adm-tab:hover { color: #e83e5a; }
        .adm-tab.active { color: #e83e5a; border-bottom-color: #e83e5a; }

        /* ── Content ── */
        .adm-content { max-width: 1200px; margin: 28px auto; padding: 0 20px; }

        /* ── Message ── */
        .adm-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .adm-msg.success { background: #d4edda; color: #155724; }
        .adm-msg.error   { background: #f8d7da; color: #721c24; }

        /* ── Stats cards ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 22px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-align: center;
        }
        .stat-num { font-size: 36px; font-weight: 800; color: #e83e5a; line-height: 1; margin-bottom: 6px; }
        .stat-label { font-size: 13px; color: #888; }

        /* ── Section title ── */
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #222; }

        /* ── Tables ── */
        .adm-table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .adm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .adm-table th { background: #f8f8f8; padding: 12px 16px; text-align: left; font-weight: 600; color: #555; border-bottom: 1px solid #eee; }
        .adm-table td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .adm-table tbody tr:last-child td { border-bottom: none; }
        .adm-table tbody tr:hover { background: #fafafa; }

        /* ── Buttons ── */
        .btn-del { background: #dc3545; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 12px; }
        .btn-del:hover { background: #c82333; }
        .btn-role { background: #6c757d; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 12px; }
        .btn-role:hover { background: #545b62; }
        .btn-role.is-admin { background: #0069d9; }
        .btn-role.is-admin:hover { background: #0056b3; }

        /* ── Search bar ── */
        .search-bar { display: flex; gap: 10px; margin-bottom: 18px; }
        .search-bar input { flex: 1; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .search-bar button { padding: 10px 20px; background: #e83e5a; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }

        /* ── Badge ── */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-admin { background: #cce5ff; color: #004085; }
        .badge-user  { background: #e2e3e5; color: #383d41; }
        .badge-ok    { background: #d4edda; color: #155724; }
        .badge-no    { background: #f8d7da; color: #721c24; }

        /* ── Recent list ── */
        .recent-list { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .recent-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .recent-item:last-child { border-bottom: none; }
        .recent-title { font-weight: 600; color: #333; }
        .recent-meta  { color: #999; font-size: 12px; }
        .recent-likes { color: #e83e5a; font-weight: 600; }

        /* ── Empty ── */
        .adm-empty { text-align: center; padding: 50px 20px; color: #bbb; }
        .adm-empty-icon { font-size: 40px; margin-bottom: 12px; }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="adm-topbar">
    <div class="adm-topbar-logo">🎀 彩妝管理後台</div>
    <div class="adm-topbar-right">
        <span>管理員：<?php echo htmlspecialchars($adminUser); ?></span>
        <a href="index.php">← 返回網站</a>
        <a href="logout.php" class="logout-btn">登出</a>
    </div>
</div>

<!-- Tab navigation -->
<div class="adm-tabs">
    <a href="?tab=stats"  class="adm-tab <?php echo $tab === 'stats'  ? 'active' : ''; ?>">📊 數據總覽</a>
    <a href="?tab=videos" class="adm-tab <?php echo $tab === 'videos' ? 'active' : ''; ?>">🎬 影片管理</a>
    <a href="?tab=users"  class="adm-tab <?php echo $tab === 'users'  ? 'active' : ''; ?>">👥 會員管理</a>
</div>

<div class="adm-content">

    <?php if ($msg): ?>
        <div class="adm-msg <?php echo htmlspecialchars($msgType); ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- ═══ 數據總覽 ═══ -->
    <?php if ($tab === 'stats'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-num"><?php echo $stats['users']; ?></div>
                <div class="stat-label">總使用者</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $stats['verified']; ?></div>
                <div class="stat-label">已驗證信箱</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $stats['videos']; ?></div>
                <div class="stat-label">影片數</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $stats['comments']; ?></div>
                <div class="stat-label">總評論數</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:<?php echo $stats['reports'] > 0 ? '#c82333' : '#28a745'; ?>">
                    <?php echo $stats['reports']; ?>
                </div>
                <div class="stat-label">待處理檢舉</div>
            </div>
        </div>

        <div class="section-title">最新上傳影片</div>
        <?php if (empty($recentVideos)): ?>
            <div class="adm-empty"><div class="adm-empty-icon">🎬</div>還沒有影片</div>
        <?php else: ?>
            <div class="recent-list">
                <?php foreach ($recentVideos as $rv): ?>
                    <div class="recent-item">
                        <div>
                            <div class="recent-title"><?php echo htmlspecialchars($rv['title']); ?></div>
                            <div class="recent-meta"><?php echo htmlspecialchars($rv['uploaded_by']); ?> &nbsp;·&nbsp; <?php echo date('m/d H:i', strtotime($rv['upload_time'])); ?></div>
                        </div>
                        <div class="recent-likes">❤️ <?php echo (int)$rv['likes']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- ═══ 影片管理 ═══ -->
    <?php elseif ($tab === 'videos'): ?>
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
                            <th style="text-align:center;">讚數</th>
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
                                <td style="text-align:center;">❤️ <?php echo (int)$v['likes']; ?></td>
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

    <!-- ═══ 會員管理 ═══ -->
    <?php elseif ($tab === 'users'): ?>
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
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $u['role'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                        <?php echo $u['role'] === 'admin' ? '管理員' : '一般'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $u['email_verified'] ? 'badge-ok' : 'badge-no'; ?>">
                                        <?php echo $u['email_verified'] ? '已驗證' : '未驗證'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['username'] !== $adminUser): ?>
                                        <form method="post" onsubmit="return confirm('確定變更「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」的身份？')">
                                            <input type="hidden" name="target_user"  value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <input type="hidden" name="current_role" value="<?php echo htmlspecialchars($u['role']); ?>">
                                            <button type="submit" name="toggle_role" value="1"
                                                class="btn-role <?php echo $u['role'] === 'admin' ? 'is-admin' : ''; ?>">
                                                <?php echo $u['role'] === 'admin' ? '降為一般' : '升為管理員'; ?>
                                            </button>
                                        </form>
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
