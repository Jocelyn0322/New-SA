<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_uri       = $_SERVER['REQUEST_URI'];
$_navHome   = strpos($_uri, '/產品/index.php') !== false;
$_navProds  = strpos($_uri, '/products.php') !== false || strpos($_uri, '/product.php') !== false;
$_navAI     = strpos($_uri, '/AI/') !== false;
$_navVideo  = strpos($_uri, '/video.php') !== false;
$_navSkin   = strpos($_uri, '/skinmatch.php') !== false || strpos($_uri, '/skin-match.php') !== false;
$_favCount  = count($_SESSION['favorite'] ?? []);
$_initial   = isset($_SESSION['user']) ? mb_strtoupper(mb_substr($_SESSION['user'], 0, 1)) : '';

// 通知未讀數（只在登入時查）
$_notifCount = 0;
if (isset($_SESSION['user'])) {
    try {
        require_once __DIR__ . '/db.php';
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY, recipient VARCHAR(100) NOT NULL,
            actor VARCHAR(100) NOT NULL, type VARCHAR(50) DEFAULT 'new_video',
            video_id INT, video_title VARCHAR(255),
            is_read BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT NOW()
        )");
        $ns = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient = ? AND is_read = FALSE");
        $ns->execute([$_SESSION['user']]);
        $_notifCount = (int)$ns->fetchColumn();
    } catch (Exception $e) { $_notifCount = 0; }
}
?>
<style>
.icon-btn { border: none !important; border-radius: 8px; }
.icon-btn:hover { border: none !important; }
.icon-btn .icon-fav { font-size: 22px; line-height: 1; }
.icon-btn .icon-cmp { font-size: 22px; line-height: 1; }
.icon-btn[data-tip], .notif-bell[data-tip], .user-chip[data-tip] { overflow: visible; position: relative; }
.icon-btn[data-tip]::before, .notif-bell[data-tip]::before, .user-chip[data-tip]::before,
.icon-btn[data-tip]::after,  .notif-bell[data-tip]::after,  .user-chip[data-tip]::after {
  pointer-events: none; opacity: 0; transition: opacity .18s;
  position: absolute; left: 50%; transform: translateX(-50%); z-index: 9999;
}
.icon-btn[data-tip]::after, .notif-bell[data-tip]::after, .user-chip[data-tip]::after {
  content: attr(data-tip);
  top: calc(100% + 9px);
  background: rgba(26,26,46,.9); color: #fff;
  font-size: 12px; font-weight: 500; white-space: nowrap;
  padding: 5px 10px; border-radius: 6px;
}
.icon-btn[data-tip]::before, .notif-bell[data-tip]::before, .user-chip[data-tip]::before {
  content: '';
  top: calc(100% + 4px);
  border: 5px solid transparent;
  border-bottom-color: rgba(26,26,46,.9);
}
.icon-btn[data-tip]:hover::before, .notif-bell[data-tip]:hover::before, .user-chip[data-tip]:hover::before,
.icon-btn[data-tip]:hover::after,  .notif-bell[data-tip]:hover::after,  .user-chip[data-tip]:hover::after { opacity: 1; }

/* ── Notification bell ── */
.notif-wrap { position: relative; }
.notif-bell { background: none; border: none; cursor: pointer; font-size: 18px; line-height: 1;
  width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
  color: #555; transition: background .15s; position: relative; }
.notif-bell:hover { background: #f4f3f8; }
.notif-badge { position: absolute; top: 2px; right: 2px; min-width: 16px; height: 16px;
  background: #c26b7c; color: #fff; font-size: 10px; font-weight: 700;
  border-radius: 99px; padding: 0 4px; display: flex; align-items: center; justify-content: center;
  line-height: 1; border: 1.5px solid #fff; }
.notif-panel { display: none; position: absolute; top: calc(100% + 10px); right: 0;
  width: 320px; background: #fff; border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,.14);
  border: 1px solid #e8e6f0; z-index: 9999; overflow: hidden; }
.notif-panel.open { display: block; }
.notif-panel-header { display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px 10px; border-bottom: 1px solid #f0eef8; }
.notif-panel-title { font-size: 14px; font-weight: 700; color: #1a1a2e; }
.notif-mark-read { background: none; border: none; font-size: 11px; color: #c26b7c;
  cursor: pointer; font-weight: 600; padding: 0; }
.notif-mark-read:hover { text-decoration: underline; }
.notif-list { max-height: 360px; overflow-y: auto; }
.notif-item { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px;
  border-bottom: 1px solid #faf9fc; text-decoration: none; color: inherit; transition: background .12s; }
.notif-item:hover { background: #fdf9fb; }
.notif-item.unread { background: #fef5f7; }
.notif-item.unread:hover { background: #fce7ec; }
.notif-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg,#c26b7c,#9d2942);
  display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;
  font-size: 14px; flex-shrink: 0; }
.notif-body { flex: 1; min-width: 0; }
.notif-text { font-size: 13px; color: #333; line-height: 1.45; }
.notif-text strong { color: #c26b7c; }
.notif-time { font-size: 11px; color: #aaa; margin-top: 3px; }
.notif-empty { padding: 36px 16px; text-align: center; color: #aaa; font-size: 13px; }
</style>
<header class="header">
  <div class="header-inner">
    <a href="/SA/New-SA/產品/index.php" class="logo">
      <div class="logo-mark">💄</div>COSMETIC
    </a>
    <nav class="nav">
      <a href="/SA/New-SA/產品/index.php"    class="nav-link <?= $_navHome   ? 'active' : '' ?>">首頁</a>
      <a href="/SA/New-SA/AI/index.php"      class="nav-link <?= $_navAI     ? 'active' : '' ?>">AI 檢測</a>
      <a href="/SA/New-SA/產品/products.php" class="nav-link <?= $_navProds  ? 'active' : '' ?>">產品</a>
      <a href="/SA/New-SA/首頁/video.php"    class="nav-link <?= $_navVideo  ? 'active' : '' ?>">影片交流</a>
    </nav>
    <div class="header-actions">
      <a href="/SA/New-SA/產品/favorite.php" class="icon-btn" data-tip="產品收藏"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 95" width="20" height="20" fill="currentColor" aria-hidden="true">
              <path d="M50,85 C35,75 10,60 10,35 C10,18 20,8 33,8 C42,8 48,13 50,20 C52,13 58,8 67,8 C80,8 90,18 90,35 C90,60 65,75 50,85 Z"/>
            </svg><?php if ($_favCount > 0): ?><span class="count"><?= $_favCount ?></span><?php endif; ?></a>
      <a href="/SA/New-SA/產品/skinmatch.php" class="icon-btn" data-tip="膚色配對"><img src="/SA/New-SA/images/weather-icon.png" width="20" height="20" alt="膚色配對" style="display:block;opacity:.75;"></a>
      <a href="/SA/New-SA/產品/compare.php"  class="icon-btn" data-tip="產品比較"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 90" width="20" height="20" fill="currentColor" aria-hidden="true">
              <!-- knob -->
              <circle cx="50" cy="7" r="6"/>
              <!-- crossbar -->
              <rect x="7" y="13" width="86" height="8" rx="4"/>
              <!-- pole -->
              <rect x="46" y="6" width="8" height="66"/>
              <!-- base -->
              <rect x="26" y="72" width="48" height="9" rx="4.5"/>
              <!-- left outer wire -->
              <polygon points="11,21 15,21 7,50 3,50"/>
              <!-- left inner wire -->
              <polygon points="11,21 15,21 35,50 31,50"/>
              <!-- left bowl rim -->
              <rect x="1" y="49" width="34" height="4" rx="2"/>
              <!-- left bowl body -->
              <path d="M2,53 Q2,65 18,65 Q34,65 34,53 Z"/>
              <!-- right outer wire -->
              <polygon points="89,21 85,21 93,50 97,50"/>
              <!-- right inner wire -->
              <polygon points="89,21 85,21 65,50 69,50"/>
              <!-- right bowl rim -->
              <rect x="65" y="49" width="34" height="4" rx="2"/>
              <!-- right bowl body -->
              <path d="M66,53 Q66,65 82,65 Q98,65 98,53 Z"/>
            </svg></a>
      <?php if (isset($_SESSION['user'])): ?>
        <div class="notif-wrap">
          <button class="notif-bell" id="notifBell" onclick="toggleNotifPanel()" aria-label="通知" data-tip="通知">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 72 72" width="20" height="20" aria-hidden="true">
              <g transform="translate(0,-980.36218)">
                <path fill="currentColor" transform="translate(0,980.36218)" d="M36,12.594c-1.609,0-4.781,0.656-4.781,0.656c-1.012,0.14-0.444,2.115-0.063,3.063c0.182,0.451,2.781,1.031,2.781,1.031l-0.063,3c0,0-4.356,0.657-5.719,1.844c-1.363,1.187-2.464,1.342-4.125,5.281c-1.038,2.464-2.282,14.221-3.094,17.875c-0.811,3.654-1.781,4.031-1.781,4.031L36,49.344l16.844,0.031c0,0-0.97-0.377-1.781-4.031c-0.811-3.654-2.056-15.411-3.094-17.875c-1.66-3.939-2.762-4.094-4.125-5.281c-1.363-1.187-5.719-1.844-5.719-1.844l-0.063-3c0,0,2.6-0.58,2.781-1.031c0.381-0.948,0.949-2.923-0.063-3.063C40.781,13.25,37.609,12.594,36,12.594z"/>
                <path fill="currentColor" d="m55,1031.716-37.969,0.031c-0.739,0.001-1.024,1.219-1.031,1.969-0.007,0.75,0.24,2.032,1,2.031l14.406,0c-0.005,0.075-0.031,0.142-0.031,0.219c0,2.514,2.075,4.563,4.625,4.563c2.55,0,4.625-2.049,4.625-4.563c0-0.088-0.025-0.164-0.031-0.25l14.406,0c0.745-0.001,0.999-1.254,1-2c0.001-0.746-0.253-2.001-1-2z"/>
              </g>
            </svg>
            <?php if ($_notifCount > 0): ?>
            <span class="notif-badge" id="notifBadge"><?= $_notifCount > 99 ? '99+' : $_notifCount ?></span>
            <?php else: ?>
            <span class="notif-badge" id="notifBadge" style="display:none">0</span>
            <?php endif; ?>
          </button>
          <div class="notif-panel" id="notifPanel">
            <div class="notif-panel-header">
              <span class="notif-panel-title">通知</span>
              <button class="notif-mark-read" onclick="markAllRead()">全部標為已讀</button>
            </div>
            <div class="notif-list" id="notifList">
              <div class="notif-empty">載入中…</div>
            </div>
          </div>
        </div>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
          <a href="/SA/New-SA/首頁/admin.php" class="btn btn-outline btn-sm">管理後台</a>
        <?php else: ?>
          <a href="/SA/New-SA/首頁/profile.php" class="user-chip" data-tip="個人資料">
            <div class="user-avatar"><?= htmlspecialchars($_initial) ?></div>
            <span><?= htmlspecialchars($_SESSION['user']) ?></span>
          </a>
        <?php endif; ?>
      <?php else: ?>
        <a href="/SA/New-SA/首頁/login.php" class="btn btn-primary btn-sm">登入</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<script>
(function(){
  function timeAgo(dateStr) {
    const diff = (Date.now() - new Date(dateStr)) / 1000;
    if (diff < 60)   return '剛剛';
    if (diff < 3600) return Math.floor(diff/60) + ' 分鐘前';
    if (diff < 86400) return Math.floor(diff/3600) + ' 小時前';
    return Math.floor(diff/86400) + ' 天前';
  }

  window.toggleNotifPanel = function() {
    const panel = document.getElementById('notifPanel');
    const isOpen = panel.classList.toggle('open');
    if (isOpen) loadNotifications();
    if (isOpen) {
      setTimeout(() => document.addEventListener('click', closeOnOutside), 0);
    }
  };

  function closeOnOutside(e) {
    const wrap = document.getElementById('notifPanel')?.closest('.notif-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('notifPanel').classList.remove('open');
      document.removeEventListener('click', closeOnOutside);
    }
  }

  function loadNotifications() {
    fetch('/SA/New-SA/notifications.php?action=list')
      .then(r => r.json())
      .then(data => {
        const list = document.getElementById('notifList');
        const badge = document.getElementById('notifBadge');
        if (badge) badge.style.display = 'none';
        if (!data.notifications || data.notifications.length === 0) {
          list.innerHTML = '<div class="notif-empty">目前沒有通知</div>';
          return;
        }
        list.innerHTML = data.notifications.map(n => {
          const initial = n.actor.charAt(0).toUpperCase();
          const title = n.video_title ? `「${n.video_title}」` : '新影片';
          const link = '/SA/New-SA/首頁/video.php';
          return `<a href="${link}" class="notif-item${n.is_read ? '' : ' unread'}">
            <div class="notif-avatar">${initial}</div>
            <div class="notif-body">
              <div class="notif-text"><strong>${n.actor}</strong> 發布了新影片 ${title}</div>
              <div class="notif-time">${timeAgo(n.created_at)}</div>
            </div>
          </a>`;
        }).join('');
      })
      .catch(() => {
        document.getElementById('notifList').innerHTML = '<div class="notif-empty">載入失敗</div>';
      });
  }

  window.markAllRead = function() {
    fetch('/SA/New-SA/notifications.php?action=mark_read', { method: 'POST' });
    const badge = document.getElementById('notifBadge');
    if (badge) badge.style.display = 'none';
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  };
})();
</script>
