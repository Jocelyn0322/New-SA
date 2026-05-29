<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../db.php';
require_once __DIR__ . '/../notify_helper.php';
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

// 軟性下架影片（從影片管理列表）
if (isset($_POST['takedown_video'])) {
    $vid    = (int)$_POST['video_id'];
    $reason = trim($_POST['removed_reason'] ?? '');
    $stmt = $pdo->prepare("SELECT title, uploaded_by FROM videos WHERE id = ?");
    $stmt->execute([$vid]);
    $vrow = $stmt->fetch();
    if ($vrow) {
        try { $pdo->exec("ALTER TABLE videos ADD COLUMN removed_reason TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE videos ADD COLUMN removed_at DATETIME"); } catch (Exception $e) {}
        $pdo->prepare("UPDATE videos SET is_active = 0, removed_reason = ?, removed_at = NOW() WHERE id = ?")
            ->execute([$reason ?: '違反社群規範', $vid]);
        $noteText = $reason ? "，原因：{$reason}" : '';
        insertNotification($pdo, $vrow['uploaded_by'], 'video_removed',
            "您的影片「{$vrow['title']}」已被管理員下架{$noteText}。如有異議可至影片交流頁面提出申訴。",
            $adminUser, $vid, $vrow['title']);
        $msg = '⬇ 已下架「' . $vrow['title'] . '」，使用者已收到通知'; $msgType = 'success';
    }
    $tab = 'videos';
}

// 復原下架影片
if (isset($_POST['restore_video'])) {
    $vid = (int)$_POST['video_id'];
    $stmt = $pdo->prepare("SELECT title, uploaded_by FROM videos WHERE id = ?");
    $stmt->execute([$vid]);
    $vrow = $stmt->fetch();
    if ($vrow) {
        $pdo->prepare("UPDATE videos SET is_active = 1, removed_reason = NULL, removed_at = NULL WHERE id = ?")
            ->execute([$vid]);
        insertNotification($pdo, $vrow['uploaded_by'], 'appeal_result',
            "您的影片「{$vrow['title']}」已由管理員恢復上架。", $adminUser);
        $msg = '✅ 已復原影片「' . $vrow['title'] . '」'; $msgType = 'success';
    }
    $tab = 'videos';
}

// 強制刪除影片
if (isset($_POST['force_delete_video'])) {
    $vid = (int)$_POST['video_id'];
    $row = $pdo->prepare("SELECT file_path, title, uploaded_by FROM videos WHERE id = ?");
    $row->execute([$vid]);
    $vrow = $row->fetch();
    if ($vrow) {
        $fp = __DIR__ . '/' . $vrow['file_path'];
        if (file_exists($fp) && is_file($fp)) unlink($fp);
        $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$vid]);
        insertNotification($pdo, $vrow['uploaded_by'], 'video_removed',
            "您的影片「{$vrow['title']}」已被管理員下架移除。", $adminUser);
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
    $cr = $pdo->prepare("SELECT username, content FROM video_comments WHERE id = ?");
    $cr->execute([$cid]);
    $crow = $cr->fetch();
    $pdo->prepare("DELETE FROM video_comments WHERE id = ?")->execute([$cid]);
    if ($crow) {
        $preview = mb_strlen($crow['content']) > 20 ? mb_substr($crow['content'], 0, 20) . '…' : $crow['content'];
        insertNotification($pdo, $crow['username'], 'comment_removed',
            "您的留言「{$preview}」已被管理員移除，請遵守社群規範。", $adminUser);
    }
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
        // 取得申請內容（需在更新前讀取）
        $sub = $pdo->prepare("SELECT * FROM product_requests WHERE id = ? AND type = 'submission'");
        $sub->execute([$subId]);
        $subRow = $sub->fetch();

        // 更新申請狀態
        $pdo->prepare("UPDATE product_requests SET status = ?, admin_note = ? WHERE id = ? AND type = 'submission'")
            ->execute([$decision, $adminNote, $subId]);

        // 核准時將產品複製進 data 表（讓使用者能在產品頁看到）
        if ($decision === 'approved' && $subRow) {
            try {
                $pdo->prepare("
                    INSERT INTO data (name, brand, category, purpose, image_url, ingredients)
                    VALUES (:name, :brand, :category, :purpose, :image_url, :ingredients)
                ")->execute([
                    ':name'        => $subRow['product_name'] ?? '',
                    ':brand'       => $subRow['brand']        ?? '',
                    ':category'    => $subRow['category'] ?: '未分類',
                    ':purpose'     => $subRow['description']  ?? '',
                    ':image_url'   => '',
                    ':ingredients' => '',
                ]);
            } catch (Exception $_e) {
                // 欄位不符時記錄但不中止審核流程
                $msg .= '（產品已核准但寫入 data 表失敗：' . $_e->getMessage() . '）';
            }
        }

        if ($subRow) {
            $resultLabel = $decision === 'approved' ? '核准' : '拒絕';
            $noteText    = $adminNote ? "，備註：{$adminNote}" : '';
            $notifMsg    = "您提交的商品「{$subRow['product_name']}」審核結果：{$resultLabel}{$noteText}。";
            insertNotification($pdo, $subRow['username'], 'product_review', $notifMsg, $adminUser);
        }

        $label = $decision === 'approved' ? '✅ 已核准' : '❌ 已拒絕';
        $msg   = "{$label}「" . ($subRow['product_name'] ?? '該商品') . "」（已傳送站內通知）";
        $msgType = 'success';
    }
    $tab = 'products';
}


// 更新產品資料
if (isset($_POST['update_product'])) {
    $pid  = (int)$_POST['product_id'];
    $name = trim($_POST['name'] ?? '');
    if ($pid > 0 && $name !== '') {
        $pdo->prepare("UPDATE data SET name=?, brand=?, category=?, origin=?, purpose=?, ingredients=?, precautions=? WHERE id=?")
            ->execute([
                $name,
                trim($_POST['brand']        ?? ''),
                trim($_POST['category']     ?? ''),
                trim($_POST['origin']       ?? ''),
                trim($_POST['purpose']      ?? ''),
                trim($_POST['ingredients']  ?? ''),
                trim($_POST['precautions']  ?? ''),
                $pid,
            ]);
        $msg = '✅ 已更新「' . htmlspecialchars($name) . '」'; $msgType = 'success';
    }
    $tab = 'data_products';
}

// 標記檢舉為已處理
if (isset($_POST['dismiss_report'])) {
    $vid = (int)$_POST['video_id'];
    $pdo->prepare("UPDATE video_reports SET status = 'resolved' WHERE video_id = ?")
        ->execute([$vid]);
    $msg = '已標記為已處理'; $msgType = 'success';
    $tab = 'reports';
}

// 審核申訴
if (isset($_POST['review_appeal'])) {
    $appealId  = (int)$_POST['appeal_id'];
    $decision  = $_POST['decision']    ?? '';
    $adminNote = trim($_POST['admin_note'] ?? '');

    if ($appealId && in_array($decision, ['approved', 'rejected'])) {
        try { $pdo->exec("ALTER TABLE video_appeals ADD COLUMN reviewed_at DATETIME"); } catch (Exception $e) {}
        $ar = $pdo->prepare("SELECT va.*, v.title AS video_title FROM video_appeals va LEFT JOIN videos v ON v.id = va.video_id WHERE va.id = ?");
        $ar->execute([$appealId]);
        $appealRow = $ar->fetch();

        if ($appealRow) {
            $pdo->prepare("UPDATE video_appeals SET status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$decision, $adminNote, $appealId]);

            if ($decision === 'approved') {
                $pdo->prepare("UPDATE videos SET is_active = 1, removed_reason = NULL, removed_at = NULL WHERE id = ?")
                    ->execute([$appealRow['video_id']]);
                $notifMsg = "您對影片「{$appealRow['video_title']}」的申訴已通過，影片已恢復上架。" . ($adminNote ? " 管理員備註：{$adminNote}" : '');
            } else {
                $notifMsg = "您對影片「{$appealRow['video_title']}」的申訴未通過，影片維持下架。" . ($adminNote ? " 管理員備註：{$adminNote}" : '');
            }
            insertNotification($pdo, $appealRow['username'], 'appeal_result', $notifMsg, $adminUser);

            $msg = $decision === 'approved' ? '✅ 申訴已核准，影片已恢復' : '❌ 申訴已拒絕';
            $msgType = 'success';
        }
    }
    $tab = 'appeals';
}

// ── 資料查詢 ───────────────────────────────────────────────

if ($tab === 'data_products') {
    $dpSearch  = trim($_GET['q'] ?? '');
    $dpPage    = max(1, (int)($_GET['p'] ?? 1));
    $dpPerPage = 25;
    $dpOffset  = ($dpPage - 1) * $dpPerPage;

    if ($dpSearch !== '') {
        $like = '%' . $dpSearch . '%';
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM data WHERE name LIKE ? OR brand LIKE ? OR category LIKE ?");
        $cntStmt->execute([$like,$like,$like]);
        $dpTotal = (int)$cntStmt->fetchColumn();
        $dpStmt  = $pdo->prepare("SELECT * FROM data WHERE name LIKE ? OR brand LIKE ? OR category LIKE ? ORDER BY id LIMIT ? OFFSET ?");
        $dpStmt->execute([$like,$like,$like,$dpPerPage,$dpOffset]);
    } else {
        $dpTotal = (int)$pdo->query("SELECT COUNT(*) FROM data")->fetchColumn();
        $dpStmt  = $pdo->prepare("SELECT * FROM data ORDER BY id LIMIT ? OFFSET ?");
        $dpStmt->execute([$dpPerPage,$dpOffset]);
    }
    $dpProducts = $dpStmt->fetchAll();
    $dpPages    = (int)ceil($dpTotal / $dpPerPage);
}

if ($tab === 'products') {
    $filterStatus = $_GET['status'] ?? 'pending';
    $allowedStatus = ['pending', 'approved', 'rejected', 'all'];
    if (!in_array($filterStatus, $allowedStatus)) $filterStatus = 'pending';

    if ($filterStatus === 'all') {
        $submissions = $pdo->query("
            SELECT ps.*, u.email
            FROM product_requests ps
            LEFT JOIN users u ON u.username = ps.username
            WHERE ps.type = 'submission'
            ORDER BY ps.created_at DESC
        ")->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT ps.*, u.email
            FROM product_requests ps
            LEFT JOIN users u ON u.username = ps.username
            WHERE ps.type = 'submission' AND ps.status = ?
            ORDER BY ps.created_at DESC
        ");
        $stmt->execute([$filterStatus]);
        $submissions = $stmt->fetchAll();
    }

    $pendingCount = $pdo->query("SELECT COUNT(*) FROM product_requests WHERE type='submission' AND status = 'pending'")->fetchColumn();
}

if ($tab === 'stats') {
    $stats['users']    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['videos']   = $pdo->query("SELECT COUNT(*) FROM videos WHERE is_active = 1")->fetchColumn();
    $stats['comments'] = $pdo->query("SELECT COUNT(*) FROM video_comments")->fetchColumn();
    $stats['reports']  = $pdo->query("SELECT COUNT(*) FROM video_reports WHERE status = 'pending'")->fetchColumn();
    $stats['new_users_week']   = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
    $stats['pending_products'] = $pdo->query("SELECT COUNT(*) FROM product_requests WHERE type='submission' AND status = 'pending'")->fetchColumn();

    // 排名資料
    try {
        $rankData['product_views'] = $pdo->query("SELECT id AS item_id, name AS item_name, COALESCE(view_count,0) AS score FROM data ORDER BY view_count DESC NULLS LAST LIMIT 10")->fetchAll();
        $rankData['product_favs']  = $pdo->query("SELECT p.id AS item_id, p.name AS item_name, COUNT(f.id) AS score FROM data p LEFT JOIN product_favorites f ON f.product_id = p.id GROUP BY p.id, p.name ORDER BY score DESC LIMIT 10")->fetchAll();
        $rankData['video_views']   = $pdo->query("SELECT id AS item_id, title AS item_name, view_count AS score FROM videos WHERE is_active = 1 ORDER BY view_count DESC LIMIT 10")->fetchAll();
        $rankData['video_likes']   = $pdo->query("SELECT v.id AS item_id, v.title AS item_name, COUNT(l.id) AS score FROM videos v LEFT JOIN likes l ON l.video_id = v.id WHERE v.is_active = 1 GROUP BY v.id, v.title ORDER BY score DESC LIMIT 10")->fetchAll();
    } catch (Throwable $e) {
        $rankData = [];
    }
}

if ($tab === 'videos') {
    $search    = trim($_GET['q']    ?? '');
    $catFilter = trim($_GET['cat']  ?? '');
    $sortMode  = trim($_GET['sort'] ?? '');
    $vPage     = max(1, (int)($_GET['p'] ?? 1));
    $vPerPage  = 5;
    $vOffset   = ($vPage - 1) * $vPerPage;

    try {
        $vCategories = $pdo->query("SELECT DISTINCT tags FROM videos WHERE is_active = 1 AND tags IS NOT NULL AND tags != '' ORDER BY tags")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { $vCategories = []; }

    $where  = "WHERE v.is_active = 1";
    $params = [];
    if ($search !== '') {
        $where .= " AND (v.title LIKE ? OR v.uploaded_by LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($catFilter !== '') {
        $where .= " AND v.tags = ?";
        $params[] = $catFilter;
    }
    if ($sortMode === 'week') {
        $where .= " AND v.upload_time >= NOW() - INTERVAL 7 DAY";
    }
    $orderBy = $sortMode === 'views'
        ? "ORDER BY v.view_count DESC, v.upload_time DESC"
        : "ORDER BY v.upload_time DESC";

    $baseSql = "
        SELECT v.id, v.title, v.uploaded_by, v.upload_time, v.file_path,
               COALESCE(v.view_count, 0) AS view_count,
               COALESCE(v.tags, '')      AS category,
               COUNT(DISTINCT r.id) AS report_count,
               COUNT(DISTINCT c.id) AS comment_count
        FROM videos v
        LEFT JOIN video_reports r ON v.id = r.video_id AND r.status = 'pending'
        LEFT JOIN video_comments c ON v.id = c.video_id
        {$where}
        GROUP BY v.id
    ";
    $cntSql = "SELECT COUNT(*) FROM (
        SELECT v.id FROM videos v
        LEFT JOIN video_reports r ON v.id = r.video_id AND r.status = 'pending'
        LEFT JOIN video_comments c ON v.id = c.video_id
        {$where} GROUP BY v.id
    ) _cnt";

    if ($params) {
        $cntStmt = $pdo->prepare($cntSql); $cntStmt->execute($params);
        $vTotal  = (int)$cntStmt->fetchColumn();
        $mainStmt = $pdo->prepare($baseSql . " {$orderBy} LIMIT {$vPerPage} OFFSET {$vOffset}");
        $mainStmt->execute($params);
        $allVideos = $mainStmt->fetchAll();
    } else {
        $vTotal    = (int)$pdo->query($cntSql)->fetchColumn();
        $allVideos = $pdo->query($baseSql . " {$orderBy} LIMIT {$vPerPage} OFFSET {$vOffset}")->fetchAll();
    }
    $vPages = (int)ceil($vTotal / $vPerPage);

    // 下架中的影片（供復原用）
    try {
        $inactiveVideos = $pdo->query("
            SELECT id, title, uploaded_by, removed_reason, removed_at
            FROM videos WHERE is_active = 0
            ORDER BY removed_at DESC NULLS LAST
        ")->fetchAll();
    } catch (Throwable $e) { $inactiveVideos = []; }
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
                  AND cr.created_at >= NOW() - INTERVAL 30 DAY
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
              AND cr.created_at >= NOW() - INTERVAL 30 DAY
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

if ($tab === 'appeals') {
    try {
        $pdo->exec("ALTER TABLE video_appeals ADD COLUMN reviewed_at DATETIME");
        $appeals = $pdo->query("
            SELECT va.id, va.video_id, va.username, va.reason, va.status, va.admin_note,
                   va.created_at, va.reviewed_at, v.title AS video_title, v.is_active
            FROM video_appeals va
            LEFT JOIN videos v ON v.id = va.video_id
            ORDER BY (va.status = 'pending') DESC, va.created_at DESC
        ")->fetchAll();
    } catch (Exception $e) {
        $appeals = [];
        $msg = '無法載入申訴資料：' . $e->getMessage(); $msgType = 'error';
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
<title>COSMETIC — 管理後台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&display=swap">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --sidebar-w: 220px;
  --sidebar-collapsed-w: 64px;
  --topbar-h: 58px;
  --sidebar-bg: #5c1a2a;
  --sidebar-hover: #7a2038;
  --sidebar-active: #9d2942;
  --accent: #c26b7c;
  --accent-light: #f9cfd8;
  --text-main: #1a1a2e;
  --text-2: #555;
  --text-3: #888;
  --bg: #f4f3f8;
  --card: #fff;
  --border: #e8e6f0;
  --r: 10px;
  --r-lg: 14px;
  --shadow: 0 2px 8px rgba(0,0,0,.07);
}
body { font-family: 'Noto Sans TC', -apple-system, system-ui, sans-serif; background: var(--bg); color: var(--text-main); font-size: 14px; display: flex; min-height: 100vh; }

/* ── Sidebar ── */
.sidebar { width: var(--sidebar-w); min-height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; z-index: 100; display: flex; flex-direction: column; box-shadow: 4px 0 20px rgba(0,0,0,.25); transition: width .28s cubic-bezier(.4,0,.2,1); overflow: hidden; }
.sidebar-logo { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,.08); }
.sidebar-logo-main { font-size: 18px; font-weight: 700; color: #fff; letter-spacing: 1px; }
.sidebar-logo-sub { font-size: 11px; color: rgba(255,255,255,.4); margin-top: 2px; }
.sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; }
.nav-group-label { font-size: 10px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase; color: rgba(255,255,255,.3); padding: 12px 20px 4px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; transition: background .15s; color: rgba(255,255,255,.65); font-size: 13.5px; text-decoration: none; position: relative; }
.nav-item:hover { background: var(--sidebar-hover); color: #fff; }
.nav-item.active { background: var(--sidebar-active); color: #fff; }
.nav-item.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--accent); border-radius: 0 2px 2px 0; }
.nav-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
.nav-badge { margin-left: auto; background: var(--accent); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px; min-width: 20px; text-align: center; }
.nav-badge.warn { background: #f0a500; }
.sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.08); display: flex; align-items: center; gap: 10px; }
.sidebar-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #fff; font-weight: 700; flex-shrink: 0; }
.sidebar-user-name { font-size: 13px; font-weight: 600; color: #fff; }
.sidebar-user-role { font-size: 11px; color: rgba(255,255,255,.4); }

/* ── Main ── */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left .28s cubic-bezier(.4,0,.2,1); }

/* ── Topbar ── */
.topbar { height: var(--topbar-h); background: var(--card); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; display: flex; align-items: center; padding: 0 28px; box-shadow: 0 1px 4px rgba(0,0,0,.06); gap: 16px; }
.topbar-title { font-size: 17px; font-weight: 700; }
.topbar-breadcrumb { font-size: 13px; color: var(--text-3); }
.topbar-spacer { flex: 1; }
.topbar-btn { height: 34px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--card); font-size: 13px; cursor: pointer; color: var(--text-2); display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: background .15s; }
.topbar-btn:hover { background: var(--bg); }
.topbar-btn.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
.topbar-btn.primary:hover { background: #b05c6c; }

/* ── Content ── */
.content { padding: 28px; flex: 1; }

/* ── Flash message ── */
.adm-msg { padding: 13px 18px; border-radius: var(--r); margin-bottom: 20px; font-size: 13px; font-weight: 500; }
.adm-msg.success { background: #eef7f1; color: #4a8a62; border-left: 3px solid #7aba96; }
.adm-msg.error   { background: #fdf0f0; color: #a05050; border-left: 3px solid #d08888; }

/* ── Stat cards ── */
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
@media(max-width:900px){ .stats-grid { grid-template-columns: repeat(2,1fr); } }
.stat-card { background: var(--card); border-radius: var(--r-lg); padding: 20px 22px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 16px; border: 1px solid var(--border); }
.stat-icon { width: 44px; height: 44px; border-radius: var(--r); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-body { min-width: 0; }
.stat-num { font-size: 26px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 12px; color: var(--text-3); font-weight: 500; white-space: nowrap; }

/* ── Section title ── */
.section-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; letter-spacing: .2px; }

/* ── Table ── */
.adm-table-wrap { background: var(--card); border-radius: var(--r-lg); box-shadow: var(--shadow); overflow: hidden; border: 1px solid var(--border); }
.adm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.adm-table th { background: #faf9fc; padding: 11px 16px; text-align: left; font-weight: 600; color: var(--text-3); border-bottom: 1px solid var(--border); font-size: 12px; letter-spacing: .4px; text-transform: uppercase; }
.adm-table td { padding: 12px 16px; border-bottom: 1px solid #f0eef8; vertical-align: middle; }
.adm-table tbody tr:last-child td { border-bottom: none; }
.adm-table tbody tr:hover td { background: #fdf9fb; }

/* ── Buttons ── */
.btn-del  { background: #fee2e2; color: #991b1b; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .15s; }
.btn-del:hover  { background: #dc2626; color: #fff; }
.btn-role { background: #ede9fe; color: #5b21b6; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .15s; }
.btn-role:hover { background: #7c3aed; color: #fff; }
.btn-role.is-admin { background: #dbeafe; color: #1d4ed8; }
.btn-role.is-admin:hover { background: #2563eb; color: #fff; }
.btn-ok { background: #d1fae5; color: #065f46; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .15s; }
.btn-ok:hover { background: #059669; color: #fff; }
.btn-group { display: flex; gap: 6px; flex-wrap: wrap; }

/* ── Search ── */
.search-bar { display: flex; gap: 10px; margin-bottom: 18px; }
.search-bar input { flex: 1; padding: 9px 14px; border: 1px solid var(--border); border-radius: var(--r); font-size: 13px; font-family: inherit; color: var(--text-main); outline: none; transition: border .15s; background: var(--card); }
.search-bar input:focus { border-color: var(--accent); }
.search-bar button { padding: 9px 22px; background: var(--accent); color: #fff; border: none; border-radius: var(--r); cursor: pointer; font-size: 13px; font-weight: 600; font-family: inherit; transition: opacity .15s; }
.search-bar button:hover { opacity: .88; }

/* ── Badges ── */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: .2px; }
.badge-admin { background: #dbeafe; color: #1d4ed8; }
.badge-user  { background: #f1f5f9; color: #475569; border: 1px solid var(--border); }
.badge-ok    { background: #d1fae5; color: #065f46; }
.badge-no    { background: #fee2e2; color: #991b1b; }
.badge-warn  { background: #ffedd5; color: #9a3412; }

/* ── Empty state ── */
.adm-empty { text-align: center; padding: 60px 20px; color: var(--text-3); }
.adm-empty-icon { font-size: 42px; margin-bottom: 12px; }

/* ── Report detail ── */
.report-detail { background: #faf9fc; padding: 14px 16px; margin-top: 8px; border-radius: var(--r); border: 1px solid var(--border); font-size: 13px; }
.report-detail-item { padding: 8px 0; border-bottom: 1px solid var(--border); }
.report-detail-item:last-child { border-bottom: none; }

/* ── Comment ── */
.comment-content { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Section Header ── */
.sec-header { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.sec-title { font-size: 20px; font-weight: 700; }
.sec-subtitle { font-size: 13px; color: var(--text-3); margin-top: 4px; }
.sec-header-spacer { flex: 1; }

/* ── Card ── */
.card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 20px; }
.card-header { padding: 16px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.card-header-title { font-size: 15px; font-weight: 700; }
.card-header-spacer { flex: 1; }

/* ── Filter Bar ── */
.filter-bar { display: flex; gap: 8px; flex-wrap: wrap; padding: 14px 22px; border-bottom: 1px solid var(--border); background: #faf9fc; }
.filter-input { height: 34px; padding: 0 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; background: var(--card); color: var(--text-main); min-width: 180px; font-family: inherit; }
.filter-input:focus { border-color: var(--accent); }
.filter-select { height: 34px; padding: 0 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; background: var(--card); color: var(--text-main); cursor: pointer; }
.filter-btn { height: 34px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--card); font-size: 13px; cursor: pointer; color: var(--text-2); transition: background .15s; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; }
.filter-btn:hover { background: var(--bg); }
.filter-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── Table wrap inside card ── */
.table-wrap { overflow-x: auto; }

/* ── Pagination ── */
.pagination { display: flex; align-items: center; gap: 6px; padding: 14px 22px; border-top: 1px solid var(--border); }
.page-info { font-size: 13px; color: var(--text-3); margin-right: auto; }
.page-btn { width: 32px; height: 32px; border-radius: 7px; border: 1px solid var(--border); background: var(--card); font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all .15s; text-decoration: none; color: var(--text-main); font-family: inherit; }
.page-btn:hover { background: var(--bg); }
.page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── Summary Row ── */
.summary-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
.summary-mini { background: var(--card); border-radius: var(--r); border: 1px solid var(--border); padding: 16px 20px; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow); }
.summary-mini-icon { font-size: 24px; }
.summary-mini-num { font-size: 22px; font-weight: 700; }
.summary-mini-label { font-size: 12px; color: var(--text-3); margin-top: 2px; }

/* ── Report Cards (video reports) ── */
.report-card-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.report-card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; }
.report-card-head { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.report-card-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
.report-card-meta { font-size: 12px; color: var(--text-3); }
.report-card-details { padding: 12px 20px; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; max-height: 180px; overflow-y: auto; }
.report-detail-row { background: #faf9fc; border-radius: 8px; padding: 8px 12px; font-size: 12px; }
.report-card-actions { padding: 12px 20px; display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Act Buttons ── */
.act-btn { height: 30px; padding: 0 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--card); font-size: 12px; cursor: pointer; color: var(--text-2); transition: all .15s; display: inline-flex; align-items: center; gap: 5px; font-family: inherit; text-decoration: none; white-space: nowrap; }
.act-btn:hover { background: var(--bg); }
.act-btn.danger { border-color: #fca5a5; color: #dc2626; }
.act-btn.danger:hover { background: #fee2e2; }
.act-btn.success { border-color: #6ee7b7; color: #059669; }
.act-btn.success:hover { background: #d1fae5; }
.act-btn.neutral { border-color: #93c5fd; color: #2563eb; }
.act-btn.neutral:hover { background: #dbeafe; }

/* ── User avatar in table ── */
.td-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-light); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--accent); flex-shrink: 0; }
.td-user { display: flex; align-items: center; gap: 10px; }

/* ── Extra badges ── */
.badge-rose { background: #fce7ec; color: #9d2942; }
.badge-blue { background: #dbeafe; color: #1d4ed8; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-orange { background: #ffedd5; color: #9a3412; }
.badge-gray { background: #f1f5f9; color: #475569; }
.badge-purple { background: #f3e8ff; color: #6b21a8; }
.badge-red { background: #fee2e2; color: #991b1b; }
.badge-suspended { background: #fee2e2; color: #991b1b; }

/* ── Collapsible Sidebar (Desktop) ── */
.sidebar.collapsed { width: var(--sidebar-collapsed-w); }
.main.sidebar-collapsed { margin-left: var(--sidebar-collapsed-w); }
.sidebar.collapsed .nav-text,
.sidebar.collapsed .nav-group-label { display: none; }
.sidebar.collapsed .sidebar-logo { padding: 16px 0; text-align: center; }
.sidebar.collapsed .sidebar-logo-sub { display: none; }
.sidebar.collapsed .nav-item { justify-content: center; padding: 10px 0; }
.sidebar.collapsed .nav-icon { width: 64px; text-align: center; font-size: 18px; }
.sidebar.collapsed .nav-badge { display: none; }
.sidebar.collapsed .sidebar-footer { justify-content: center; padding: 14px 0; }
.sidebar.collapsed .sidebar-nav { padding: 6px 0; }

/* Nav collapse toggle row */
.nav-collapse-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 20px; cursor: pointer;
  color: rgba(255,255,255,.4); font-size: 13px;
  transition: background .15s, color .15s;
  border-top: 1px solid rgba(255,255,255,.08);
  user-select: none; flex-shrink: 0;
}
.nav-collapse-row:hover { background: rgba(255,255,255,.07); color: rgba(255,255,255,.75); }
.nav-collapse-icon { font-size: 17px; width: 20px; text-align: center; flex-shrink: 0; display: inline-block; transition: transform .28s; }
.sidebar.collapsed .nav-collapse-row { justify-content: center; padding: 10px 0; }
@media (max-width: 768px) { .nav-collapse-row { display: none; } }

/* ── Mobile sidebar ── */
.sidebar-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 99;
  backdrop-filter: blur(2px);
}
.sidebar-overlay.open { display: block; }
.mob-sidebar-toggle {
  display: none; background: none; border: none; cursor: pointer;
  width: 36px; height: 36px; border-radius: 8px;
  align-items: center; justify-content: center;
  color: var(--text-2); flex-shrink: 0; transition: background .15s; margin-right: 8px;
}
.mob-sidebar-toggle:hover { background: var(--bg); }
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform .28s cubic-bezier(.4,0,.2,1);
  }
  .sidebar.open { transform: translateX(0); }
  .main { margin-left: 0; }
  .mob-sidebar-toggle { display: flex; }
  .content { padding: 16px; }
  .topbar { padding: 0 12px; gap: 8px; }
  .topbar-breadcrumb { display: none; }
  .topbar-title { font-size: 14px; }
  .topbar-btn { padding: 0 8px; font-size: 12px; white-space: nowrap; }
  .card { overflow: visible; }
  .report-card-grid { grid-template-columns: 1fr !important; }
  .appeal-inner { display: flex !important; flex-direction: column !important; }
  .appeal-action-panel { min-width: 0 !important; border-left: none !important; border-top: 1px solid #f3eef0; }
}
</style>
</head>
<body>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAdminSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="adminSidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-main">💄<span class="nav-text"> COSMETIC</span></div>
    <div class="sidebar-logo-sub nav-text">管理後台</div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-group-label">概覽</div>
    <a href="?tab=stats" class="nav-item <?php echo $tab==='stats' ? 'active':''; ?>">
      <span class="nav-icon">📊</span><span class="nav-text"> 數據統計</span>
    </a>

    <div class="nav-group-label">內容管理</div>
    <a href="?tab=videos" class="nav-item <?php echo $tab==='videos' ? 'active':''; ?>">
      <span class="nav-icon">🎬</span><span class="nav-text"> 影片管理</span>
    </a>
    <a href="?tab=comments" class="nav-item <?php echo $tab==='comments' ? 'active':''; ?>">
      <span class="nav-icon">💬</span><span class="nav-text"> 留言管理</span>
    </a>
    <a href="?tab=reports" class="nav-item <?php echo $tab==='reports' ? 'active':''; ?>">
      <span class="nav-icon">🚩</span><span class="nav-text"> 檢舉管理</span>
    </a>
    <a href="?tab=appeals" class="nav-item <?php echo $tab==='appeals' ? 'active':''; ?>">
      <span class="nav-icon">📋</span><span class="nav-text"> 申訴管理</span>
      <?php try {
        $apCount = $pdo->query("SELECT COUNT(*) FROM video_appeals WHERE status='pending'")->fetchColumn();
        if ($apCount > 0): ?><span class="nav-badge"><?php echo (int)$apCount; ?></span><?php endif;
      } catch (Exception $e) {} ?>
    </a>

    <div class="nav-group-label">產品管理</div>
    <a href="<?= BASE_URL ?>/產品/report_manage.php" class="nav-item">
      <span class="nav-icon">⚠️</span><span class="nav-text"> 商品回報</span>
    </a>
    <a href="?tab=data_products" class="nav-item <?php echo $tab==='data_products' ? 'active':''; ?>">
      <span class="nav-icon">🗄️</span><span class="nav-text"> 資料庫產品</span>
    </a>
    <a href="?tab=products" class="nav-item <?php echo $tab==='products' ? 'active':''; ?>">
      <span class="nav-icon">🛍️</span><span class="nav-text"> 商品審核</span>
      <?php
      $badgeCount = $pdo->query("SELECT COUNT(*) FROM product_requests WHERE type='submission' AND status='pending'")->fetchColumn();
      if ($badgeCount > 0): ?>
        <span class="nav-badge warn"><?php echo (int)$badgeCount; ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-group-label">會員</div>
    <a href="?tab=users" class="nav-item <?php echo $tab==='users' ? 'active':''; ?>">
      <span class="nav-icon">👥</span><span class="nav-text"> 使用者管理</span>
    </a>
  </nav>

  <!-- 收合按鈕（桌面版） -->
  <div class="nav-collapse-row" id="navCollapseRow" onclick="collapseAdminSidebar()" title="收起/展開選單">
    <span class="nav-collapse-icon" id="navCollapseIcon">‹</span>
    <span class="nav-text">收起選單</span>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?php echo strtoupper(substr($adminUser, 0, 1)); ?></div>
    <div class="nav-text">
      <div class="sidebar-user-name"><?php echo htmlspecialchars($adminUser); ?></div>
      <div class="sidebar-user-role">超級管理員</div>
    </div>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <div class="topbar">
    <button class="mob-sidebar-toggle" onclick="toggleAdminSidebar()" aria-label="選單">
      <svg width="18" height="14" viewBox="0 0 18 14" fill="currentColor">
        <rect width="18" height="2.2" rx="1.1"/>
        <rect y="5.9" width="18" height="2.2" rx="1.1"/>
        <rect y="11.8" width="18" height="2.2" rx="1.1"/>
      </svg>
    </button>
    <div>
      <?php
        $tabTitles = [
          'stats' => '數據統計', 'videos' => '影片管理', 'comments' => '留言管理',
          'reports' => '檢舉管理', 'appeals' => '申訴管理', 'data_products' => '資料庫產品',
          'products' => '商品審核', 'users' => '使用者管理'
        ];
        $currentTitle = $tabTitles[$tab] ?? '管理後台';
      ?>
      <div class="topbar-title"><?php echo $currentTitle; ?></div>
      <div class="topbar-breadcrumb">後台管理 / <?php echo $currentTitle; ?></div>
    </div>
    <div class="topbar-spacer"></div>
    <a href="<?= BASE_URL ?>/產品/index.php" class="topbar-btn">← 返回網站</a>
    <a href="logout.php" class="topbar-btn primary">登出</a>
  </div>

  <div class="content">

<?php if ($msg): ?>
    <div class="adm-msg <?php echo htmlspecialchars($msgType); ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if ($tab === 'stats'): ?>
<!-- ══════════ 數據總覽 ══════════ -->
<div class="sec-header">
  <div>
    <div class="sec-title">數據統計</div>
    <div class="sec-subtitle">平台整體概覽與排名統計</div>
  </div>
  <div class="sec-header-spacer"></div>
</div>
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



<?php elseif ($tab === 'videos'): ?>
<!-- ══════════ 影片管理 ══════════ -->
<?php
  $qVal  = htmlspecialchars($_GET['q']   ?? '');
  $catVal= htmlspecialchars($_GET['cat'] ?? '');
  $sVal  = htmlspecialchars($sortMode    ?? '');
  function vLink($extra=''){return '?tab=videos'.$extra;}
?>
<div class="sec-header">
  <div>
    <div class="sec-title">影片管理</div>
    <div class="sec-subtitle">管理所有使用者上傳的影片</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-header-title">全部影片</div>
    <span class="badge badge-blue"><?php echo $vTotal ?? 0; ?> 部</span>
    <div class="card-header-spacer"></div>
  </div>

  <form method="get">
    <input type="hidden" name="tab" value="videos">
    <?php if ($sortMode): ?><input type="hidden" name="sort" value="<?php echo $sVal; ?>"><?php endif; ?>
    <div class="filter-bar">
      <input class="filter-input" type="text" name="q" placeholder="🔍 搜尋影片標題或作者..." value="<?php echo $qVal; ?>" style="min-width:200px;">
      <?php if (!empty($vCategories)): ?>
        <select name="cat" class="filter-select" onchange="this.form.submit()">
          <option value="">全部分類</option>
          <?php foreach ($vCategories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($catFilter===$cat)?'selected':''; ?>><?php echo htmlspecialchars($cat); ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button type="submit" class="filter-btn active">搜尋</button>
      <?php if ($qVal || $catVal): ?>
        <a href="?tab=videos<?php echo $sVal?'&sort='.$sVal:''; ?>" class="filter-btn">✕ 清除</a>
      <?php endif; ?>
      <div style="margin-left:auto;display:flex;gap:6px;">
        <?php $baseQ = ($qVal?'&q='.$qVal:'').($catVal?'&cat='.$catVal:''); ?>
        <a href="?tab=videos<?php echo $baseQ; ?>" class="filter-btn <?php echo !$sortMode?'active':''; ?>">全部</a>
        <a href="?tab=videos&sort=week<?php echo $baseQ; ?>" class="filter-btn <?php echo $sortMode==='week'?'active':''; ?>">本週新增</a>
        <a href="?tab=videos&sort=views<?php echo $baseQ; ?>" class="filter-btn <?php echo $sortMode==='views'?'active':''; ?>">最多讀</a>
      </div>
    </div>
  </form>

  <?php if (empty($allVideos)): ?>
    <div class="adm-empty"><div class="adm-empty-icon">🎬</div><div>找不到影片</div></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th style="width:52px;">縮圖</th>
            <th>標題</th>
            <th>作者</th>
            <th>分類</th>
            <th style="text-align:right;">讀數</th>
            <th style="text-align:center;">留言</th>
            <th>上傳時間</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allVideos as $vi => $v): ?>
          <tr>
            <td style="color:var(--text-3);font-size:12px;"><?php echo $vOffset + $vi + 1; ?></td>
            <td>
              <video width="48" height="48"
                style="border-radius:8px;object-fit:cover;cursor:pointer;background:#f0eef8;display:block;"
                preload="metadata" muted playsinline
                onloadedmetadata="this.currentTime=0.1"
                onclick='openVideoModal(<?php echo json_encode($v["file_path"]); ?>, <?php echo json_encode($v["title"]); ?>)'>
                <source src="<?php echo htmlspecialchars($v['file_path']); ?>" type="video/mp4">
              </video>
            </td>
            <td>
              <strong><?php echo htmlspecialchars($v['title']); ?></strong>
              <?php if ($v['report_count'] > 0): ?>
                <span class="badge badge-orange" style="margin-left:6px;font-size:10px;">⚠️ <?php echo (int)$v['report_count']; ?></span>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-2);"><?php echo htmlspecialchars($v['uploaded_by']); ?></td>
            <td>
              <?php if ($v['category']): ?>
                <span class="badge badge-rose"><?php echo htmlspecialchars($v['category']); ?></span>
              <?php else: ?><span style="color:#ccc;">—</span><?php endif; ?>
            </td>
            <td style="text-align:right;color:var(--text-2);font-size:13px;"><?php echo number_format((int)$v['view_count']); ?></td>
            <td style="text-align:center;color:var(--text-3);">💬 <?php echo (int)$v['comment_count']; ?></td>
            <td style="color:var(--text-3);font-size:12px;"><?php echo date('Y-m-d', strtotime($v['upload_time'])); ?></td>
            <td>
              <div class="btn-group">
                <button class="act-btn neutral" onclick='openVideoModal(<?php echo json_encode($v["file_path"]); ?>, <?php echo json_encode($v["title"]); ?>)'>👁 檢視</button>
                <form method="post" onsubmit="return adminTakedownPrompt(this)" style="margin:0;">
                  <input type="hidden" name="video_id" value="<?php echo (int)$v['id']; ?>">
                  <input type="hidden" name="removed_reason" value="">
                  <input type="hidden" name="takedown_video" value="1">
                  <button type="submit" class="act-btn danger">⬇ 下架</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
      $pageQ = ($qVal?'&q='.$qVal:'').($catVal?'&cat='.$catVal:'').($sVal?'&sort='.$sVal:'');
    ?>
    <div class="pagination">
      <div class="page-info">共 <?php echo $vTotal; ?> 筆，顯示第 <?php echo $vOffset+1; ?>–<?php echo min($vOffset+$vPerPage,$vTotal); ?> 筆</div>
      <?php if ($vPage > 1): ?>
        <a href="?tab=videos&p=<?php echo $vPage-1; ?><?php echo $pageQ; ?>" class="page-btn">‹</a>
      <?php endif; ?>
      <?php for ($pi=1; $pi<=$vPages; $pi++): ?>
        <a href="?tab=videos&p=<?php echo $pi; ?><?php echo $pageQ; ?>" class="page-btn <?php echo $pi===$vPage?'active':''; ?>"><?php echo $pi; ?></a>
      <?php endfor; ?>
      <?php if ($vPage < $vPages): ?>
        <a href="?tab=videos&p=<?php echo $vPage+1; ?><?php echo $pageQ; ?>" class="page-btn">›</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ── 影片播放 Modal ── -->
<div id="vModalBg" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:3000;align-items:center;justify-content:center;" onclick="if(event.target===this)closeVModal()">
  <div style="background:#1a1a2e;border-radius:16px;width:92%;max-width:920px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.6);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;">
      <div id="vModalTitle" style="font-size:15px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:80%;"></div>
      <button onclick="closeVModal()" style="background:rgba(255,255,255,.12);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:16px;line-height:1;flex-shrink:0;">✕</button>
    </div>
    <video id="vModalPlayer" controls style="width:100%;max-height:72vh;background:#000;display:block;">
      <source id="vModalSrc" src="" type="video/mp4">
    </video>
  </div>
</div>
<script>
function openVideoModal(src, title) {
  document.getElementById('vModalSrc').src = src;
  document.getElementById('vModalTitle').textContent = title;
  document.getElementById('vModalPlayer').load();
  var bg = document.getElementById('vModalBg');
  bg.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeVModal() {
  document.getElementById('vModalBg').style.display = 'none';
  var p = document.getElementById('vModalPlayer');
  p.pause();
  document.getElementById('vModalSrc').src = '';
  p.load();
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeVModal(); });
function adminTakedownPrompt(form) {
  const r = prompt('請輸入下架原因（可留空，預設為「違反社群規範」）：');
  if (r === null) return false;
  form.querySelector('[name="removed_reason"]').value = r;
  return true;
}
</script>


<div style="margin-top:36px;border-top:2px solid #f0eef8;padding-top:28px;">
  <div style="font-size:15px;font-weight:700;color:#c26b7c;margin-bottom:14px;">
    ⬇ 下架中的影片
    <span style="font-size:13px;font-weight:400;color:#aaa;margin-left:6px;">共 <?php echo count($inactiveVideos); ?> 部</span>
  </div>
  <?php if (empty($inactiveVideos)): ?>
    <div style="text-align:center;padding:30px 0;color:#ccc;font-size:14px;">目前沒有下架中的影片</div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($inactiveVideos as $iv): ?>
      <div style="background:#fff8f8;border:1px solid #f5c6c6;border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
          <div style="font-weight:700;font-size:14px;color:#333;"><?php echo htmlspecialchars($iv['title']); ?></div>
          <div style="font-size:12px;color:#aaa;margin-top:3px;">
            上傳者：<strong style="color:#666;"><?php echo htmlspecialchars($iv['uploaded_by']); ?></strong>
            <?php if ($iv['removed_at']): ?>
              &nbsp;·&nbsp;下架時間：<?php echo date('Y/m/d H:i', strtotime($iv['removed_at'])); ?>
            <?php endif; ?>
            <?php if (!empty($iv['removed_reason'])): ?>
              &nbsp;·&nbsp;原因：<?php echo htmlspecialchars($iv['removed_reason']); ?>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;">
          <form method="post" onsubmit="return confirm('確定復原此影片上架？')">
            <input type="hidden" name="video_id" value="<?php echo (int)$iv['id']; ?>">
            <button type="submit" name="restore_video" value="1"
              style="padding:7px 14px;background:#eafaf1;border:1.5px solid #a9dfbf;color:#27ae60;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
              🔄 復原上架
            </button>
          </form>
          <form method="post" onsubmit="return confirm('確定永久刪除？此操作無法復原。')">
            <input type="hidden" name="video_id" value="<?php echo (int)$iv['id']; ?>">
            <button type="submit" name="force_delete_video" value="1"
              style="padding:7px 14px;background:#fde8e8;border:1.5px solid #f5c6c6;color:#c0392b;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
              🗑 永久刪除
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'comments'): ?>
<!-- ══════════ 留言管理 ══════════ -->
<div class="sec-header">
  <div>
    <div class="sec-title">留言管理</div>
    <div class="sec-subtitle">管理影片的使用者留言，含檢舉標記</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-header-title">全部留言</div>
    <span class="badge badge-blue"><?php echo count($allComments ?? []); ?> 則</span>
    <div class="card-header-spacer"></div>
  </div>
  <form method="get">
    <input type="hidden" name="tab" value="comments">
    <div class="filter-bar">
      <input class="filter-input" type="text" name="q" placeholder="🔍 搜尋留言者或留言內容..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
      <button type="submit" class="filter-btn active">搜尋</button>
      <?php if (!empty($_GET['q'])): ?><a href="?tab=comments" class="filter-btn">✕ 清除</a><?php endif; ?>
    </div>
  </form>
  <?php if (empty($allComments)): ?>
    <div class="adm-empty"><div class="adm-empty-icon">💬</div><div>目前沒有留言</div></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="adm-table">
        <thead>
          <tr><th>留言者</th><th>所屬影片</th><th>留言內容</th><th style="text-align:center;">檢舉</th><th>時間</th><th>操作</th></tr>
        </thead>
        <tbody>
          <?php foreach ($allComments as $c): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?php echo strtoupper(substr($c['username'], 0, 1)); ?></div>
                <strong><?php echo htmlspecialchars($c['username']); ?></strong>
              </div>
            </td>
            <td style="color:var(--text-3);font-size:12px;"><?php echo htmlspecialchars($c['video_title'] ?? '（已刪除）'); ?></td>
            <td><div class="comment-content" title="<?php echo htmlspecialchars($c['content']); ?>"><?php echo htmlspecialchars($c['content']); ?></div></td>
            <td style="text-align:center;">
              <?php if ($c['report_count'] > 0): ?>
                <span class="badge badge-orange">⚠️ <?php echo (int)$c['report_count']; ?></span>
              <?php else: ?><span style="color:#ccc;">—</span><?php endif; ?>
            </td>
            <td style="color:var(--text-3);font-size:12px;"><?php echo date('m/d H:i', strtotime($c['created_at'])); ?></td>
            <td>
              <div class="btn-group">
                <?php if ($c['report_count'] > 0): ?>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="comment_id" value="<?php echo (int)$c['id']; ?>">
                    <button type="submit" name="dismiss_comment_report" value="1" class="act-btn success">✓ 已處理</button>
                  </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('確定刪除這則留言？')" style="margin:0;">
                  <input type="hidden" name="comment_id" value="<?php echo (int)$c['id']; ?>">
                  <button type="submit" name="delete_comment" value="1" class="act-btn danger">🗑 刪除</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="pagination">
      <div class="page-info">共 <?php echo count($allComments); ?> 則留言</div>
    </div>
  <?php endif; ?>
</div>


<?php elseif ($tab === 'reports'): ?>
<!-- ══════════ 檢舉管理 ══════════ -->
<?php $totalReportCount = array_sum(array_column($reportedVideos ?? [], 'report_count')); ?>
<div class="sec-header">
  <div>
    <div class="sec-title">檢舉管理</div>
    <div class="sec-subtitle">使用者檢舉的影片，請審核並決定處置</div>
  </div>
  <div class="sec-header-spacer"></div>
  <?php if (!empty($reportedVideos)): ?>
    <span class="badge badge-orange" style="font-size:13px;padding:6px 14px;align-self:center;">⚠️ <?php echo count($reportedVideos); ?> 部影片待審核</span>
  <?php endif; ?>
</div>

<div class="summary-row">
  <div class="summary-mini"><div class="summary-mini-icon">⏳</div><div><div class="summary-mini-num"><?php echo count($reportedVideos ?? []); ?></div><div class="summary-mini-label">待審核影片</div></div></div>
  <div class="summary-mini"><div class="summary-mini-icon">🚩</div><div><div class="summary-mini-num"><?php echo $totalReportCount; ?></div><div class="summary-mini-label">總檢舉件數</div></div></div>
  <div class="summary-mini"><div class="summary-mini-icon">📋</div><div><div class="summary-mini-num"><?php echo max(0, count($reportedVideos ?? []) > 0 ? $totalReportCount : 0); ?></div><div class="summary-mini-label">需要關注</div></div></div>
</div>

<?php if (empty($reportedVideos)): ?>
  <div class="adm-empty"><div class="adm-empty-icon">✅</div><div style="font-size:15px;font-weight:600;margin-bottom:6px;">目前沒有待處理的檢舉</div></div>
<?php else: ?>
  <div class="report-card-grid">
  <?php foreach ($reportedVideos as $rv): ?>
    <div class="report-card">
      <div class="report-card-head">
        <div style="flex:1;min-width:0;">
          <div class="report-card-title"><?php echo htmlspecialchars($rv['title']); ?></div>
          <div class="report-card-meta">上傳者：<?php echo htmlspecialchars($rv['uploaded_by']); ?> · 最後檢舉：<?php echo date('m/d H:i', strtotime($rv['last_reported_at'])); ?></div>
        </div>
        <span class="badge badge-orange" style="flex-shrink:0;"><?php echo (int)$rv['report_count']; ?> 件</span>
      </div>
      <?php if (!empty($detailsByVideo[$rv['id']])): ?>
        <div class="report-card-details">
          <?php foreach ($detailsByVideo[$rv['id']] as $d): ?>
            <div class="report-detail-row">
              <strong><?php echo htmlspecialchars($d['reported_by']); ?></strong>
              <span class="badge badge-orange" style="margin:0 6px;"><?php echo htmlspecialchars($d['reason']); ?></span>
              <?php if ($d['description']): ?><span style="color:var(--text-2);"><?php echo htmlspecialchars($d['description']); ?></span><?php endif; ?>
              <span style="color:var(--text-3);font-size:11px;float:right;"><?php echo date('m/d H:i', strtotime($d['created_at'])); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="report-card-actions">
        <a href="video.php?video=<?php echo (int)$rv['id']; ?>" target="_blank" class="act-btn neutral">▶ 查看</a>
        <form method="post" style="margin:0;">
          <input type="hidden" name="video_id" value="<?php echo (int)$rv['id']; ?>">
          <button type="submit" name="dismiss_report" value="1" class="act-btn success">✓ 標記已處理</button>
        </form>
        <form method="post" onsubmit="return confirm('確定強制刪除這部影片？')" style="margin:0;">
          <input type="hidden" name="video_id" value="<?php echo (int)$rv['id']; ?>">
          <input type="hidden" name="from_reports" value="1">
          <button type="submit" name="force_delete_video" value="1" class="act-btn danger">🗑 強制刪除</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>


<?php elseif ($tab === 'products'): ?>
<!-- ══════════ 商品審核 ══════════ -->
<div class="sec-header">
  <div>
    <div class="sec-title">商品審核</div>
    <div class="sec-subtitle">審核使用者提交的商品申請</div>
  </div>
  <div class="sec-header-spacer"></div>
  <?php if ($pendingCount > 0): ?>
    <span class="badge badge-orange" style="font-size:13px;padding:6px 14px;align-self:center;">⏳ <?php echo (int)$pendingCount; ?> 件待審核</span>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-header-title">申請清單</div>
    <span class="badge badge-blue"><?php echo count($submissions ?? []); ?> 筆</span>
    <div class="card-header-spacer"></div>
  </div>
  <div class="filter-bar">
    <?php foreach(['pending'=>'待審核','approved'=>'已通過','rejected'=>'已拒絕','all'=>'全部'] as $s=>$label): ?>
      <a href="?tab=products&status=<?php echo $s; ?>" class="filter-btn <?php echo ($filterStatus===$s)?'active':''; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($submissions)): ?>
  <div class="adm-empty"><div class="adm-empty-icon">🛍️</div>目前沒有<?php echo $filterStatus==='pending'?'待審核的':($filterStatus==='all'?'':($filterStatus==='approved'?'已通過的':'已拒絕的')); ?>商品申請</div>
<?php else: ?>
  <?php foreach ($submissions as $sub): ?>
    <?php
      $statusBadge = match($sub['status']) {
        'pending'  => '<span class="badge badge-orange">⏳ 待審核</span>',
        'approved' => '<span class="badge badge-green">✅ 已通過</span>',
        'rejected' => '<span class="badge badge-red">❌ 已拒絕</span>',
        default    => ''
      };
    ?>
    <div class="card" style="margin-bottom:14px;">
      <div class="card-header">
        <div style="flex:1;min-width:0;">
          <div style="font-size:15px;font-weight:700;margin-bottom:3px;"><?php echo htmlspecialchars($sub['product_name']); ?> <?php echo $statusBadge; ?></div>
          <div style="font-size:12px;color:var(--text-3);line-height:1.8;">
            <?php if ($sub['brand']): ?>品牌：<?php echo htmlspecialchars($sub['brand']); ?> &nbsp;·&nbsp; <?php endif; ?>
            <?php if ($sub['category']): ?>分類：<?php echo htmlspecialchars($sub['category']); ?> &nbsp;·&nbsp; <?php endif; ?>
            <?php if ($sub['price']): ?>售價：<?php echo htmlspecialchars($sub['price']); ?> &nbsp;·&nbsp; <?php endif; ?>
            申請者：<strong><?php echo htmlspecialchars($sub['username']); ?></strong>
            （<?php echo $sub['email'] ? htmlspecialchars($sub['email']) : '無 Email'; ?>）&nbsp;·&nbsp;
            <?php echo date('Y/m/d H:i', strtotime($sub['created_at'])); ?>
          </div>
        </div>
      </div>
      <div style="padding:14px 22px;">
        <?php if ($sub['description']): ?>
          <div style="font-size:13px;color:var(--text-2);background:var(--bg);border-radius:8px;padding:10px 12px;margin-bottom:10px;"><?php echo nl2br(htmlspecialchars($sub['description'])); ?></div>
        <?php endif; ?>
        <?php if ($sub['purchase_link']): ?>
          <div style="font-size:12px;margin-bottom:8px;"><a href="<?php echo htmlspecialchars($sub['purchase_link']); ?>" target="_blank" rel="noopener" style="color:#2563eb;">🔗 查看購買連結</a></div>
        <?php endif; ?>
        <?php if ($sub['admin_note'] && $sub['status'] !== 'pending'): ?>
          <div style="font-size:12px;color:var(--text-3);border-left:3px solid var(--border);padding-left:10px;margin-bottom:8px;">管理員備註：<?php echo htmlspecialchars($sub['admin_note']); ?></div>
        <?php endif; ?>
        <?php if ($sub['status'] === 'pending'): ?>
        <div style="border-top:1px solid var(--border);margin-top:10px;padding-top:12px;">
          <form method="post">
            <input type="hidden" name="submission_id" value="<?php echo (int)$sub['id']; ?>">
            <label style="font-size:12px;font-weight:600;color:var(--text-3);display:block;margin-bottom:4px;">管理員備註（寄信時顯示）</label>
            <textarea name="admin_note" rows="2" placeholder="可填入核准原因、建議修改內容等..."
              style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;box-sizing:border-box;margin-bottom:8px;outline:none;resize:vertical;"></textarea>
            <div class="btn-group">
              <button type="submit" name="review_submission" value="1"
                onclick="this.form.querySelector('[name=decision]').value='approved';return confirm('確定核准「<?php echo htmlspecialchars(addslashes($sub['product_name'])); ?>」？');"
                class="act-btn success" style="height:34px;padding:0 16px;">✅ 核准</button>
              <input type="hidden" name="decision" value="">
              <button type="submit" name="review_submission" value="1"
                onclick="this.form.querySelector('[name=decision]').value='rejected';return confirm('確定拒絕「<?php echo htmlspecialchars(addslashes($sub['product_name'])); ?>」？');"
                class="act-btn danger" style="height:34px;padding:0 16px;">❌ 拒絕</button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <p style="color:var(--text-3);font-size:13px;margin-top:4px;">共 <?php echo count($submissions); ?> 筆</p>
<?php endif; ?>


<?php elseif ($tab === 'users'): ?>
<!-- ══════════ 會員管理 ══════════ -->
<style>
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

<div class="sec-header">
  <div>
    <div class="sec-title">使用者管理</div>
    <div class="sec-subtitle">管理會員帳號、身份與違規狀態</div>
  </div>
</div>

<div class="summary-row" style="margin-bottom:20px;">
  <div class="summary-mini">
    <div class="summary-mini-icon">👥</div>
    <div><div class="summary-mini-num"><?php echo count($allUsers); ?></div><div class="summary-mini-label">總會員數</div></div>
  </div>
  <div class="summary-mini">
    <div class="summary-mini-icon">⚠️</div>
    <div><div class="summary-mini-num" style="color:<?php echo $warningCount>0?'#c08020':'inherit'; ?>"><?php echo $warningCount; ?></div><div class="summary-mini-label">違規警告中（3次以上）</div></div>
  </div>
  <div class="summary-mini">
    <div class="summary-mini-icon">🚫</div>
    <div><div class="summary-mini-num" style="color:<?php echo $suspendedCount>0?'#c05050':'inherit'; ?>"><?php echo $suspendedCount; ?></div><div class="summary-mini-label">已停用帳號</div></div>
  </div>
</div>

<div class="card" style="margin-bottom:10px;">
  <div class="card-header">
    <div class="card-header-title">會員清單</div>
    <span class="badge badge-blue"><?php echo count($allUsers); ?> 位</span>
    <div class="card-header-spacer"></div>
    <span style="font-size:12px;color:var(--text-3);">ℹ️ 30天內留言被標記達 5 次即自動停用</span>
  </div>

<?php if (empty($allUsers)): ?>
  <div class="adm-empty"><div class="adm-empty-icon">👥</div>沒有使用者資料</div>
<?php else: ?>
  <div class="table-wrap">
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
            <div class="td-user">
              <div class="td-avatar"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></div>
              <div>
                <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                <?php if ($isSuspended): ?>
                  <div style="font-size:11px;color:#c05050;margin-top:1px;">停用於 <?php echo $u['suspended_at'] ? date('m/d H:i', strtotime($u['suspended_at'])) : '—'; ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="color:var(--text-3);font-size:12px;"><?php echo htmlspecialchars($u['email'] ?: '—'); ?></td>
          <td>
            <span class="badge <?php echo $u['role']==='admin' ? 'badge-blue' : 'badge-gray'; ?>">
              <?php echo $u['role']==='admin' ? '管理員' : '一般'; ?>
            </span>
          </td>
          <td>
            <span class="badge <?php echo $u['email_verified'] ? 'badge-green' : 'badge-red'; ?>">
              <?php echo $u['email_verified'] ? '已驗證' : '未驗證'; ?>
            </span>
          </td>
          <td style="text-align:center;">
            <?php if ($u['role'] === 'admin'): ?>
              <span style="color:var(--text-3);font-size:12px;">—</span>
            <?php else: ?>
              <div class="vio-bar" title="本月違規 <?php echo $violations; ?>/5 次">
                <?php for ($vi = 1; $vi <= 5; $vi++): ?>
                  <div class="vio-dot <?php echo $vi <= $violations ? 'filled' : 'empty'; ?>"></div>
                <?php endfor; ?>
              </div>
              <span style="font-size:11px;color:<?php echo $violations>=5?'#c05050':($isWarning?'#c08020':'var(--text-3)'); ?>;margin-left:4px;">
                <?php echo $violations; ?>/5<?php if ($violations>=5): ?> 已達上限<?php elseif ($isWarning): ?> ⚠️<?php endif; ?>
              </span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isSuspended): ?>
              <span class="badge badge-suspended">🚫 已停用</span>
            <?php elseif ($isWarning): ?>
              <span class="badge badge-orange">⚠️ 警告</span>
            <?php else: ?>
              <span class="badge badge-green">正常</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-3);font-size:12px;"><?php echo $u['created_at'] ? date('Y/m/d', strtotime($u['created_at'])) : '—'; ?></td>
          <td>
            <?php if ($u['username'] !== $adminUser): ?>
              <div class="btn-group">
                <?php if ($u['role'] !== 'admin'): ?>
                  <form method="post" onsubmit="return confirm('確定變更「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」的身份？')">
                    <input type="hidden" name="target_user"  value="<?php echo htmlspecialchars($u['username']); ?>">
                    <input type="hidden" name="current_role" value="<?php echo htmlspecialchars($u['role']); ?>">
                    <button type="submit" name="toggle_role" value="1" class="act-btn neutral">升為管理員</button>
                  </form>
                <?php else: ?>
                  <form method="post" onsubmit="return confirm('確定降級「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」？')">
                    <input type="hidden" name="target_user"  value="<?php echo htmlspecialchars($u['username']); ?>">
                    <input type="hidden" name="current_role" value="admin">
                    <button type="submit" name="toggle_role" value="1" class="act-btn neutral">降為一般</button>
                  </form>
                <?php endif; ?>
                <?php if ($isSuspended): ?>
                  <form method="post" onsubmit="return confirm('確定恢復「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」的帳號？')">
                    <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($u['username']); ?>">
                    <button type="submit" name="restore_user" value="1" class="act-btn success">✓ 恢復</button>
                  </form>
                <?php elseif ($u['role'] !== 'admin'): ?>
                  <form method="post" onsubmit="return confirm('確定停用「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」帳號？')">
                    <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($u['username']); ?>">
                    <button type="submit" name="suspend_user" value="1" class="act-btn danger">🚫 停用</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color:var(--text-3);font-size:12px;">（目前帳號）</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <div class="page-info">共 <?php echo count($allUsers); ?> 位使用者</div>
  </div>
<?php endif; ?>
</div>

<?php elseif ($tab === 'data_products'): ?>
<!-- ══════════ 產品管理 ══════════ -->
<style>
/* 編輯 Modal */
.dp-modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:2000; align-items:center; justify-content:center; }
.dp-modal-bg.open { display:flex; }
.dp-modal { background:#fff; border-radius:18px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; padding:28px 28px 24px; box-shadow:0 12px 48px rgba(0,0,0,.22); }
.dp-modal h3 { margin:0 0 20px; font-size:17px; color:#3a2a2a; }
.dp-field { margin-bottom:14px; }
.dp-field label { display:block; font-size:12px; font-weight:600; color:#9a7878; margin-bottom:4px; }
.dp-field input, .dp-field textarea, .dp-field select { width:100%; padding:9px 12px; border:1.5px solid #f0d5dc; border-radius:9px; font-size:14px; font-family:inherit; box-sizing:border-box; outline:none; }
.dp-field input:focus, .dp-field textarea:focus, .dp-field select:focus { border-color:#c47a8a; }
.dp-field textarea { resize:vertical; min-height:72px; }
.dp-modal-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.dp-modal-btns { display:flex; gap:10px; margin-top:18px; }
.dp-modal-save { flex:1; padding:10px; background:#c47a8a; color:#fff; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; }
.dp-modal-save:hover { background:#b3647a; }
.dp-modal-cancel { padding:10px 20px; background:#f0e8e8; color:#7a3040; border:none; border-radius:10px; font-size:14px; cursor:pointer; }
</style>

<div class="sec-header">
  <div>
    <div class="sec-title">資料庫產品</div>
    <div class="sec-subtitle">管理系統產品資料庫，共 <?php echo $dpTotal; ?> 筆產品</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-header-title">產品列表</div>
    <span class="badge badge-blue"><?php echo $dpTotal; ?> 筆</span>
    <div class="card-header-spacer"></div>
  </div>
  <form method="get">
    <input type="hidden" name="tab" value="data_products">
    <div class="filter-bar">
      <input class="filter-input" type="text" name="q" value="<?php echo htmlspecialchars($dpSearch); ?>" placeholder="🔍 搜尋名稱、品牌、分類…">
      <button type="submit" class="filter-btn active">搜尋</button>
      <?php if ($dpSearch): ?><a href="?tab=data_products" class="filter-btn">✕ 清除</a><?php endif; ?>
    </div>
  </form>
  <div class="table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>#</th>
          <th>品牌</th>
          <th>名稱</th>
          <th>分類</th>
          <th>產地</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($dpProducts as $p): ?>
        <tr>
          <td style="color:var(--text-3);font-size:12px;"><?php echo $p['id']; ?></td>
          <td><?php echo htmlspecialchars($p['brand'] ?? ''); ?></td>
          <td style="font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></td>
          <td><span class="badge badge-rose" style="font-size:11px;"><?php echo htmlspecialchars($p['category'] ?? ''); ?></span></td>
          <td style="color:var(--text-3);"><?php echo htmlspecialchars($p['origin'] ?? ''); ?></td>
          <td>
            <button class="act-btn neutral" onclick='openEdit(<?php echo htmlspecialchars(json_encode([
              "id"           => $p["id"],
              "name"         => $p["name"]         ?? "",
              "brand"        => $p["brand"]        ?? "",
              "category"     => $p["category"]     ?? "",
              "origin"       => $p["origin"]       ?? "",
              "purpose"      => $p["purpose"]      ?? "",
              "ingredients"  => $p["ingredients"]  ?? "",
              "precautions"  => $p["precautions"]  ?? "",
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)'>✏️ 編輯</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($dpPages > 1): ?>
  <div class="pagination">
    <div class="page-info">第 <?php echo $dpPage; ?> / <?php echo $dpPages; ?> 頁，共 <?php echo $dpTotal; ?> 筆</div>
    <?php for ($i = 1; $i <= $dpPages; $i++): ?>
      <a href="?tab=data_products&p=<?php echo $i; ?>&q=<?php echo urlencode($dpSearch); ?>"
         class="page-btn <?php echo $i === $dpPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php else: ?>
  <div class="pagination">
    <div class="page-info">共 <?php echo $dpTotal; ?> 筆產品</div>
  </div>
  <?php endif; ?>
</div>

<!-- 編輯 Modal -->
<div id="dpModalBg" class="dp-modal-bg" onclick="if(event.target===this)closeEdit()">
    <div class="dp-modal">
        <h3>編輯產品</h3>
        <form method="post">
            <input type="hidden" name="product_id" id="dp_id">
            <div class="dp-modal-row">
                <div class="dp-field">
                    <label>品牌</label>
                    <input type="text" name="brand" id="dp_brand">
                </div>
                <div class="dp-field">
                    <label>分類</label>
                    <select name="category" id="dp_category">
                        <?php foreach(['底妝','眼影','腮紅','口紅','唇彩','唇釉','唇油','唇膏','唇泥','睫毛膏','眼線','打亮','修容','帶亮','遮瑕','護膚','防曬'] as $cat): ?>
                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dp-field">
                <label>產品名稱 *</label>
                <input type="text" name="name" id="dp_name" required>
            </div>
            <div class="dp-field">
                <label>產地</label>
                <input type="text" name="origin" id="dp_origin">
            </div>
            <div class="dp-field">
                <label>用途</label>
                <textarea name="purpose" id="dp_purpose"></textarea>
            </div>
            <div class="dp-field">
                <label>成分</label>
                <textarea name="ingredients" id="dp_ingredients"></textarea>
            </div>
            <div class="dp-field">
                <label>注意事項</label>
                <textarea name="precautions" id="dp_precautions"></textarea>
            </div>
            <div class="dp-modal-btns">
                <button type="submit" name="update_product" value="1" class="dp-modal-save">儲存</button>
                <button type="button" class="dp-modal-cancel" onclick="closeEdit()">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(p) {
    document.getElementById('dp_id').value          = p.id;
    document.getElementById('dp_name').value        = p.name;
    document.getElementById('dp_brand').value       = p.brand;
    document.getElementById('dp_origin').value      = p.origin;
    document.getElementById('dp_purpose').value     = p.purpose;
    document.getElementById('dp_ingredients').value = p.ingredients;
    document.getElementById('dp_precautions').value = p.precautions;
    var sel = document.getElementById('dp_category');
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === p.category) { sel.selectedIndex = i; break; }
    }
    document.getElementById('dpModalBg').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeEdit() {
    document.getElementById('dpModalBg').classList.remove('open');
    document.body.style.overflow = '';
}
</script>

<?php elseif ($tab === 'appeals'): ?>
  <div class="sec-head" style="margin-bottom:20px;">
    <div class="sec-title">申訴管理</div>
    <div class="sec-subtitle">使用者對下架影片提出的申訴，審核後請通知當事人</div>
  </div>

  <?php if (empty($appeals)): ?>
    <div style="text-align:center;padding:60px 0;color:#bbb;font-size:15px;">目前沒有申訴記錄</div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($appeals as $ap):
        $isPending = $ap['status'] === 'pending';
        $statusLabel = ['pending'=>'⏳ 待審核','approved'=>'✅ 已核准','rejected'=>'❌ 已拒絕'][$ap['status']] ?? $ap['status'];
        $statusColor = ['pending'=>'#856404','approved'=>'#155724','rejected'=>'#721c24'][$ap['status']] ?? '#555';
        $statusBg    = ['pending'=>'#fff3cd','approved'=>'#d4edda','rejected'=>'#f8d7da'][$ap['status']] ?? '#eee';
    ?>
    <div style="background:#fff;border-radius:14px;border:1px solid #ede8ea;box-shadow:0 2px 8px rgba(0,0,0,.05);overflow:hidden;<?php echo $isPending ? '' : 'opacity:.7;'; ?>">
      <div class="appeal-inner" style="display:grid;grid-template-columns:1fr auto;align-items:start;gap:0;">
        <div style="padding:18px 20px;">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <span style="font-weight:700;color:#c26b7c;font-size:15px;"><?php echo htmlspecialchars($ap['video_title'] ?? '（影片已刪除）'); ?></span>
            <span style="padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600;background:<?php echo $statusBg; ?>;color:<?php echo $statusColor; ?>"><?php echo $statusLabel; ?></span>
          </div>
          <div style="font-size:13px;color:#555;margin-bottom:6px;">申訴者：<strong><?php echo htmlspecialchars($ap['username']); ?></strong></div>
          <div style="font-size:13px;color:#333;line-height:1.6;background:#fdf9fb;border-left:3px solid #c26b7c;padding:8px 12px;border-radius:0 6px 6px 0;margin-bottom:8px;"><?php echo nl2br(htmlspecialchars($ap['reason'])); ?></div>
          <div style="font-size:12px;color:#aaa;">提交時間：<?php echo date('Y/m/d H:i', strtotime($ap['created_at'])); ?></div>
          <?php if ($ap['admin_note']): ?>
            <div style="font-size:12px;color:#888;margin-top:6px;">管理員備註：<?php echo htmlspecialchars($ap['admin_note']); ?></div>
          <?php endif; ?>
        </div>
        <?php if ($isPending): ?>
        <div class="appeal-action-panel" style="padding:16px 18px;border-left:1px solid #f3eef0;min-width:220px;display:flex;flex-direction:column;gap:10px;">
          <form method="post">
            <input type="hidden" name="appeal_id" value="<?php echo (int)$ap['id']; ?>">
            <input type="hidden" name="decision" value="approved">
            <textarea name="admin_note" placeholder="核准備註（可選）" rows="2" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:8px;font-size:12px;resize:none;margin-bottom:6px;font-family:inherit;"></textarea>
            <button type="submit" name="review_appeal" value="1" style="width:100%;padding:8px;background:#eafaf1;border:1.5px solid #a9dfbf;color:#27ae60;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">✔ 核准申訴（恢復影片）</button>
          </form>
          <form method="post" onsubmit="return confirm('確定拒絕此申訴？')">
            <input type="hidden" name="appeal_id" value="<?php echo (int)$ap['id']; ?>">
            <input type="hidden" name="decision" value="rejected">
            <textarea name="admin_note" placeholder="拒絕原因（建議填寫）" rows="2" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:8px;font-size:12px;resize:none;margin-bottom:6px;font-family:inherit;"></textarea>
            <button type="submit" name="review_appeal" value="1" style="width:100%;padding:8px;background:#fde8e8;border:1.5px solid #f5c6c6;color:#c0392b;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">✕ 拒絕申訴</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>
  </div><!-- /content -->
</div><!-- /main -->

<script>
function toggleRank(group, btn) {
    var rows = document.querySelectorAll('[data-group="' + group + '"].rank-more');
    var expanded = btn.dataset.expanded === '1';
    rows.forEach(function(r) { r.style.display = expanded ? 'none' : 'flex'; });
    btn.dataset.expanded = expanded ? '0' : '1';
    btn.textContent = expanded ? '▾ 查看更多' : '▴ 收起';
}

function toggleAdminSidebar() {
  document.getElementById('adminSidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeAdminSidebar() {
  document.getElementById('adminSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}
// 點選選單項目後自動關閉 sidebar（手機）
document.querySelectorAll('.nav-item').forEach(function(el) {
  el.addEventListener('click', function() {
    if (window.innerWidth <= 768) closeAdminSidebar();
  });
});

// ── 桌面 Sidebar 收合 ──
function collapseAdminSidebar() {
  var sidebar   = document.getElementById('adminSidebar');
  var main      = document.querySelector('.main');
  var icon      = document.getElementById('navCollapseIcon');
  var collapsed = sidebar.classList.toggle('collapsed');
  if (collapsed) {
    main.classList.add('sidebar-collapsed');
    if (icon) icon.textContent = '›';
    localStorage.setItem('adminSidebarCollapsed', '1');
  } else {
    main.classList.remove('sidebar-collapsed');
    if (icon) icon.textContent = '‹';
    localStorage.setItem('adminSidebarCollapsed', '0');
  }
}
// 恢復上次收合狀態
(function() {
  if (window.innerWidth <= 768) return;
  if (localStorage.getItem('adminSidebarCollapsed') === '1') {
    var sidebar = document.getElementById('adminSidebar');
    var main    = document.querySelector('.main');
    var icon    = document.getElementById('navCollapseIcon');
    if (sidebar) sidebar.classList.add('collapsed');
    if (main)    main.classList.add('sidebar-collapsed');
    if (icon)    icon.textContent = '›';
  }
})();
</script>
</body>
</html>
