<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_uri       = $_SERVER['REQUEST_URI'];
$_navHome   = strpos($_uri, '/產品/index.php') !== false;
$_navProds  = strpos($_uri, '/products.php') !== false || strpos($_uri, '/product.php') !== false;
$_navAI     = strpos($_uri, '/AI/') !== false;
$_navVideo  = strpos($_uri, '/video.php') !== false;
$_favCount  = count($_SESSION['favorite'] ?? []);
$_initial   = isset($_SESSION['user']) ? mb_strtoupper(mb_substr($_SESSION['user'], 0, 1)) : '';
?>
<header class="header">
  <div class="header-inner">
    <a href="/SA/New-SA/產品/index.php" class="logo">
      <div class="logo-mark">💄</div>COSMETIC
    </a>
    <nav class="nav">
      <a href="/SA/New-SA/產品/index.php"    class="nav-link <?= $_navHome   ? 'active' : '' ?>">首頁</a>
      <a href="/SA/New-SA/產品/products.php" class="nav-link <?= $_navProds  ? 'active' : '' ?>">產品</a>
      <a href="/SA/New-SA/AI/index.php"      class="nav-link <?= $_navAI     ? 'active' : '' ?>">AI 檢測</a>
      <a href="/SA/New-SA/首頁/video.php"    class="nav-link <?= $_navVideo  ? 'active' : '' ?>">影片交流</a>
    </nav>
    <div class="header-actions">
      <a href="/SA/New-SA/產品/favorite.php" class="icon-btn" title="收藏">♡<?php if ($_favCount > 0): ?><span class="count"><?= $_favCount ?></span><?php endif; ?></a>
      <a href="/SA/New-SA/產品/compare.php"  class="icon-btn" title="比較">⚖</a>
      <?php if (isset($_SESSION['user'])): ?>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
          <a href="/SA/New-SA/首頁/admin.php" class="btn btn-outline btn-sm">管理後台</a>
        <?php else: ?>
          <a href="/SA/New-SA/首頁/profile.php" class="user-chip">
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
