<?php
session_start();
require __DIR__ . '/../db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: products.php');
    exit;
}

$adminUser = $_SESSION['user'] ?? 'Admin';

// 標記已處理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    $pdo->prepare("UPDATE product_requests SET status = 'resolved' WHERE id = ? AND type = 'report'")->execute([intval($_POST['resolve_id'])]);
    header('Location: report_manage.php?status=' . ($_GET['status'] ?? 'pending'));
    exit;
}

// 刪除回報
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM product_requests WHERE id = ? AND type = 'report'")->execute([intval($_POST['delete_id'])]);
    header('Location: report_manage.php?status=' . ($_GET['status'] ?? 'pending'));
    exit;
}

// 封存表（可復原）
$pdo->exec("CREATE TABLE IF NOT EXISTS deleted_products (
    id INT PRIMARY KEY, name VARCHAR(255), brand VARCHAR(255), category VARCHAR(255),
    snapshot LONGTEXT, deleted_by VARCHAR(100), deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 刪除整個產品（封存可復原；只移除 data 與 products 鏡像，其餘關聯保留以便復原）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    $pid = intval($_POST['delete_product_id']);
    if ($pid > 0) {
        $dr = $pdo->prepare("SELECT * FROM data WHERE id = ?");
        $dr->execute([$pid]);
        $drow = $dr->fetch(PDO::FETCH_ASSOC);
        if ($drow) {
            $pdo->prepare("INSERT INTO deleted_products (id, name, brand, category, snapshot, deleted_by)
                           VALUES (?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE name=VALUES(name), brand=VALUES(brand),
                               category=VALUES(category), snapshot=VALUES(snapshot),
                               deleted_by=VALUES(deleted_by), deleted_at=NOW()")
                ->execute([$pid, $drow['name'] ?? '', $drow['brand'] ?? '', $drow['category'] ?? '',
                           json_encode($drow, JSON_UNESCAPED_UNICODE), $adminUser]);
            try { $pdo->prepare("DELETE FROM data WHERE id = ?")->execute([$pid]); } catch (Exception $e) {}
            try { $pdo->prepare("DELETE FROM products WHERE p_id = ?")->execute([$pid]); } catch (Exception $e) {}
            try { $pdo->prepare("UPDATE product_requests SET status = 'resolved' WHERE product_id = ? AND type = 'report'")->execute([$pid]); } catch (Exception $e) {}
        }
    }
    header('Location: report_manage.php?status=' . ($_GET['status'] ?? 'pending'));
    exit;
}

// 恢復產品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_product_id'])) {
    $pid = intval($_POST['restore_product_id']);
    if ($pid > 0) {
        $sr = $pdo->prepare("SELECT snapshot FROM deleted_products WHERE id = ?");
        $sr->execute([$pid]);
        $snap = $sr->fetchColumn();
        $row  = $snap ? json_decode($snap, true) : null;
        if ($row && is_array($row)) {
            try {
                $cols   = array_keys($row);
                $colSql = implode(',', array_map(fn($c) => "`$c`", $cols));
                $place  = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO data ($colSql) VALUES ($place)")->execute(array_values($row));
                $pdo->prepare("DELETE FROM deleted_products WHERE id = ?")->execute([$pid]);
            } catch (Throwable $e) {}
        }
    }
    header('Location: report_manage.php?status=' . ($_GET['status'] ?? 'pending'));
    exit;
}

$statusFilter = $_GET['status'] ?? 'pending';
$whereStatus  = $statusFilter === 'resolved'
    ? "AND r.status = 'resolved'"
    : "AND (r.status IS NULL OR r.status = 'pending')";

// 已刪除（可復原）的產品
try {
    $deletedProducts = $pdo->query("SELECT id, name, brand, category, deleted_by, deleted_at
                                    FROM deleted_products ORDER BY deleted_at DESC")->fetchAll();
} catch (Throwable $e) { $deletedProducts = []; }

try {
    $reports = $pdo->query("
        SELECT r.id, r.username, r.product_id, r.report_type, r.description,
               r.status, r.created_at,
               d.name AS product_name, d.brand AS product_brand, d.category AS product_category
        FROM product_requests r
        LEFT JOIN data d ON d.id = r.product_id
        WHERE r.type = 'report' $whereStatus
        ORDER BY r.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $reports  = [];
    $dbError  = $e->getMessage();
}

$pendingCount  = (int)$pdo->query("SELECT COUNT(*) FROM product_requests WHERE type='report' AND (status = 'pending' OR status IS NULL)")->fetchColumn();
$totalCount    = (int)$pdo->query("SELECT COUNT(*) FROM product_requests WHERE type='report'")->fetchColumn();
$resolvedCount = $totalCount - $pendingCount;

$typeLabel = [
    'discontinued'   => '已停產',
    'new_version'    => '有新版本',
    'wrong_info'     => '資訊有誤',
    'ai_not_suitable'=> 'AI推薦不適合',
    'other'          => '其他',
];
$typeBadge = [
    'discontinued'   => 'badge-red',
    'new_version'    => 'badge-blue',
    'wrong_info'     => 'badge-orange',
    'ai_not_suitable'=> 'badge-purple',
    'other'          => 'badge-gray',
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>商品回報管理 — COSMETIC 後台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&display=swap">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --sidebar-w: 220px;
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
.sidebar { width: var(--sidebar-w); min-height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; z-index: 100; display: flex; flex-direction: column; box-shadow: 4px 0 20px rgba(0,0,0,.25); }
.sidebar-logo { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,.08); }
.sidebar-logo-main { font-size: 18px; font-weight: 700; color: #fff; letter-spacing: 1px; }
.sidebar-logo-sub  { font-size: 11px; color: rgba(255,255,255,.4); margin-top: 2px; }
.sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; }
.nav-group-label { font-size: 10px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase; color: rgba(255,255,255,.3); padding: 12px 20px 4px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; transition: background .15s; color: rgba(255,255,255,.65); font-size: 13.5px; text-decoration: none; position: relative; }
.nav-item:hover  { background: var(--sidebar-hover); color: #fff; }
.nav-item.active { background: var(--sidebar-active); color: #fff; }
.nav-item.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--accent); border-radius: 0 2px 2px 0; }
.nav-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
.nav-badge { margin-left: auto; background: var(--accent); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px; min-width: 20px; text-align: center; }
.nav-badge.warn { background: #f0a500; }
.sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.08); display: flex; align-items: center; gap: 10px; }
.sidebar-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #fff; font-weight: 700; flex-shrink: 0; }
.sidebar-user-name { font-size: 13px; font-weight: 600; color: #fff; }
.sidebar-user-role { font-size: 11px; color: rgba(255,255,255,.4); }

/* ── Layout ── */
.main    { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar  { height: var(--topbar-h); background: var(--card); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; display: flex; align-items: center; padding: 0 28px; box-shadow: 0 1px 4px rgba(0,0,0,.06); gap: 16px; }
.topbar-title { font-size: 17px; font-weight: 700; }
.topbar-spacer { flex: 1; }
.topbar-btn { height: 34px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--card); font-size: 13px; cursor: pointer; color: var(--text-2); display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: background .15s; }
.topbar-btn:hover { background: var(--bg); }
.content { padding: 28px; flex: 1; }

/* ── Stats ── */
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--shadow); padding: 18px 20px; display: flex; align-items: center; gap: 14px; }
.stat-icon { font-size: 22px; width: 44px; height: 44px; border-radius: var(--r); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-num   { font-size: 24px; font-weight: 800; line-height: 1; margin-bottom: 3px; }
.stat-label { font-size: 12px; color: var(--text-3); }

/* ── Filter tabs ── */
.filter-row { display: flex; gap: 8px; margin-bottom: 20px; }
.filter-tab { padding: 6px 20px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1.5px solid var(--border); color: var(--text-3); background: var(--card); transition: all .15s; }
.filter-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }

/* ── Report cards ── */
.rp-list { display: flex; flex-direction: column; gap: 12px; }
.rp-card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--shadow); display: grid; grid-template-columns: 210px 1fr 148px; overflow: hidden; }
.rp-card.resolved { opacity: .6; }

.rp-product { padding: 18px 20px; border-right: 1px solid var(--border); }
.rp-id      { font-size: 11px; color: #ccc; margin-bottom: 6px; }
.rp-name    { font-size: 14px; font-weight: 700; color: var(--accent); text-decoration: none; display: block; margin-bottom: 4px; line-height: 1.4; }
.rp-name:hover { text-decoration: underline; }
.rp-brand   { font-size: 12px; color: var(--text-3); }

.rp-content { padding: 18px 20px; }
.rp-badges  { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.badge-red    { background: #fde8e8; color: #c0392b; }
.badge-blue   { background: #dbeafe; color: #1d4ed8; }
.badge-orange { background: #fef3c7; color: #b45309; }
.badge-purple { background: #f3e8ff; color: #7c3aed; }
.badge-gray   { background: #f1f5f9; color: #64748b; }
.badge-green  { background: #d1fae5; color: #065f46; }
.rp-desc    { font-size: 13px; color: var(--text-2); line-height: 1.5; margin-bottom: 8px; }
.rp-desc.empty { color: #ccc; font-style: italic; }
.rp-who     { font-size: 12px; color: #bbb; }

.rp-actions { padding: 14px 16px; border-left: 1px solid var(--border); display: flex; flex-direction: column; gap: 7px; background: #fdfbfc; }
.rp-btn { padding: 7px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; text-align: center; cursor: pointer; text-decoration: none; border: 1.5px solid var(--border); background: var(--card); color: var(--text-2); transition: all .15s; font-family: inherit; }
.rp-btn:hover         { background: var(--bg); border-color: #c9b8be; }
.rp-resolve           { border-color: #a9dfbf; color: #27ae60; }
.rp-resolve:hover     { background: #eafaf1; }
.rp-del-report        { border-color: #f5c6c6; color: #c0392b; }
.rp-del-report:hover  { background: #fde8e8; }
.rp-del-product       { border-color: #f5c6c6; background: #fff5f5; color: #c0392b; font-weight: 700; }
.rp-del-product:hover { background: #fde8e8; }

/* ── Empty ── */
.empty-state { text-align: center; padding: 70px 20px; color: #ccc; }
.empty-icon  { font-size: 44px; margin-bottom: 12px; }

/* ── Error ── */
.db-error { background: #fff0f0; border: 1px solid #f5c6c6; border-radius: var(--r); padding: 16px 20px; color: #c0392b; font-size: 13px; }

/* ── Mobile ── */
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 99; backdrop-filter: blur(2px); }
.sidebar-overlay.open { display: block; }
.mob-sidebar-toggle { display: none; background: none; border: none; cursor: pointer; width: 36px; height: 36px; border-radius: 8px; align-items: center; justify-content: center; color: var(--text-2); flex-shrink: 0; transition: background .15s; margin-right: 8px; }
.mob-sidebar-toggle:hover { background: var(--bg); }
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); transition: transform .28s cubic-bezier(.4,0,.2,1); }
  .sidebar.open { transform: translateX(0); }
  .main { margin-left: 0; }
  .mob-sidebar-toggle { display: flex; }
  .content { padding: 16px; }
  .topbar { padding: 0 12px; gap: 8px; }
  .topbar-btn { padding: 0 8px; font-size: 12px; white-space: nowrap; }
  .stat-row { grid-template-columns: repeat(3, 1fr); }
  .rp-card { grid-template-columns: 1fr !important; }
  .rp-actions { border-left: none !important; border-top: 1px solid var(--border); flex-direction: row; flex-wrap: wrap; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAdminSidebar()"></div>
<!-- Sidebar -->
<aside class="sidebar" id="adminSidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-main">💄 COSMETIC</div>
    <div class="sidebar-logo-sub">管理後台</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-group-label">概覽</div>
    <a href="<?= BASE_URL ?>/首頁/admin.php?tab=stats" class="nav-item"><span class="nav-icon">📊</span> 數據統計</a>

    <div class="nav-group-label">內容管理</div>
    <a href="<?= BASE_URL ?>/首頁/admin.php?tab=videos"   class="nav-item"><span class="nav-icon">🎬</span> 影片管理</a>
    <a href="<?= BASE_URL ?>/首頁/admin.php?tab=comments" class="nav-item"><span class="nav-icon">💬</span> 留言管理</a>
    <a href="<?= BASE_URL ?>/首頁/admin.php?tab=reports"  class="nav-item"><span class="nav-icon">🚩</span> 檢舉管理</a>

    <div class="nav-group-label">產品管理</div>
    <a href="<?= BASE_URL ?>/產品/report_manage.php" class="nav-item active">
      <span class="nav-icon">⚠️</span> 商品回報
      <?php if ($pendingCount > 0): ?>
        <span class="nav-badge warn"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/首頁/admin.php?tab=data_products" class="nav-item"><span class="nav-icon">🗄️</span> 資料庫產品</a>
    <a href="<?= BASE_URL ?>/首頁/admin.php?tab=products"      class="nav-item"><span class="nav-icon">🛍️</span> 商品審核</a>

    <div class="nav-group-label">會員</div>
    <a href="<?= BASE_URL ?>/首頁/admin.php?tab=users" class="nav-item"><span class="nav-icon">👥</span> 使用者管理</a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?= strtoupper(substr($adminUser, 0, 1)) ?></div>
    <div>
      <div class="sidebar-user-name"><?= htmlspecialchars($adminUser) ?></div>
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
    <div class="topbar-title">商品回報管理</div>
    <div class="topbar-spacer"></div>
    <a href="<?= BASE_URL ?>/首頁/admin.php" class="topbar-btn">← 返回後台</a>
  </div>

  <div class="content">

    <!-- 統計 -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-icon" style="background:#fff0f0;">⏳</div>
        <div><div class="stat-num" style="color:#c0392b;"><?= $pendingCount ?></div><div class="stat-label">待審核回報</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#eafaf1;">✅</div>
        <div><div class="stat-num" style="color:#27ae60;"><?= $resolvedCount ?></div><div class="stat-label">已審核</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#f0eef8;">📋</div>
        <div><div class="stat-num"><?= $totalCount ?></div><div class="stat-label">累計回報</div></div>
      </div>
    </div>

    <!-- 篩選 -->
    <div class="filter-row">
      <a href="?status=pending"  class="filter-tab <?= $statusFilter !== 'resolved' ? 'active' : '' ?>">待審核</a>
      <a href="?status=resolved" class="filter-tab <?= $statusFilter === 'resolved' ? 'active' : '' ?>">已審核</a>
    </div>

    <?php if (!empty($dbError)): ?>
      <div class="db-error">資料庫錯誤：<?= htmlspecialchars($dbError) ?></div>
    <?php elseif (empty($reports)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        目前沒有<?= $statusFilter === 'resolved' ? '已審核的' : '待審核的' ?>回報
      </div>
    <?php else: ?>
    <div class="rp-list">
      <?php foreach ($reports as $r):
        $type     = $r['report_type'];
        $resolved = ($r['status'] ?? '') === 'resolved';
      ?>
      <div class="rp-card <?= $resolved ? 'resolved' : '' ?>">

        <!-- 產品資訊 -->
        <div class="rp-product">
          <div class="rp-id">#<?= $r['id'] ?></div>
          <a href="product.php?id=<?= (int)$r['product_id'] ?>" class="rp-name">
            <?= htmlspecialchars($r['product_name'] ?? '（查無商品）') ?>
          </a>
          <div class="rp-brand">
            <?= htmlspecialchars($r['product_brand'] ?? '') ?>
            <?php if ($r['product_category']): ?> · <?= htmlspecialchars($r['product_category']) ?><?php endif; ?>
          </div>
        </div>

        <!-- 回報內容 -->
        <div class="rp-content">
          <div class="rp-badges">
            <span class="badge <?= $typeBadge[$type] ?? 'badge-gray' ?>"><?= $typeLabel[$type] ?? $type ?></span>
            <?php if ($resolved): ?>
              <span class="badge badge-green">已處理</span>
            <?php endif; ?>
          </div>
          <div class="rp-desc <?= empty($r['description']) ? 'empty' : '' ?>">
            <?= $r['description'] ? htmlspecialchars($r['description']) : '無說明' ?>
          </div>
          <div class="rp-who">
            <?= htmlspecialchars($r['username']) ?> · <?= $r['created_at'] ? substr($r['created_at'], 0, 16) : '—' ?>
          </div>
        </div>

        <!-- 操作 -->
        <div class="rp-actions">
          <a href="product.php?id=<?= (int)$r['product_id'] ?>" class="rp-btn">查看產品</a>
          <?php if (!$resolved): ?>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="resolve_id" value="<?= $r['id'] ?>">
            <button type="submit" class="rp-btn rp-resolve">✔ 標記已審核</button>
          </form>
          <?php endif; ?>
          <form method="POST" style="margin:0;" onsubmit="return confirm('確定刪除此回報記錄？');">
            <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
            <button type="submit" class="rp-btn rp-del-report">刪除回報</button>
          </form>
          <?php if ($r['product_id']): ?>
          <form method="POST" style="margin:0;" onsubmit="return confirm('⚠️ 確定刪除整個商品「<?= addslashes(htmlspecialchars($r['product_name'] ?? '')) ?>」？\n（已留存於資料庫，可在下方「已刪除產品」恢復）');">
            <input type="hidden" name="delete_product_id" value="<?= (int)$r['product_id'] ?>">
            <button type="submit" class="rp-btn rp-del-product">🗑 刪除產品</button>
          </form>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 已刪除產品（可復原）：只在「已審核」分頁顯示 -->
    <?php if ($statusFilter === 'resolved' && !empty($deletedProducts)): ?>
    <div style="margin-top:26px;">
      <div style="font-size:15px;font-weight:700;color:#9c2132;margin-bottom:12px;">🗑 已刪除產品（可復原）</div>
      <div class="rp-list">
        <?php foreach ($deletedProducts as $dp): ?>
        <div class="rp-card resolved">
          <div class="rp-product">
            <div class="rp-id">#<?= (int)$dp['id'] ?></div>
            <div class="rp-name"><?= htmlspecialchars($dp['name'] ?? '') ?></div>
            <div class="rp-brand">
              <?= htmlspecialchars($dp['brand'] ?? '') ?>
              <?php if (!empty($dp['category'])): ?> · <?= htmlspecialchars($dp['category']) ?><?php endif; ?>
            </div>
          </div>
          <div class="rp-content">
            <div class="rp-badges"><span class="badge badge-gray">已刪除</span></div>
            <div class="rp-who">
              刪除者：<?= htmlspecialchars($dp['deleted_by'] ?? '—') ?> ·
              <?= $dp['deleted_at'] ? substr($dp['deleted_at'], 0, 16) : '—' ?>
            </div>
          </div>
          <div class="rp-actions">
            <form method="POST" style="margin:0;" onsubmit="return confirm('確定恢復此產品？');">
              <input type="hidden" name="restore_product_id" value="<?= (int)$dp['id'] ?>">
              <button type="submit" class="rp-btn rp-resolve">↩ 恢復產品</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<script>
function toggleAdminSidebar() {
  document.getElementById('adminSidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeAdminSidebar() {
  document.getElementById('adminSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}
</script>
</body>
</html>
