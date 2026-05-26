<?php
session_start();
include __DIR__ . '/../db.php';

$sql = "SELECT *, id AS p_id FROM data ORDER BY created_at DESC LIMIT 6";
$result = $conn->query($sql);
$favorites = $_SESSION['favorite'] ?? [];

// 真實統計數字
$statProducts = (int)$pdo->query("SELECT COUNT(*) FROM data")->fetchColumn();
$statUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$statRatings  = (int)$pdo->query("SELECT COUNT(*) FROM product_ratings")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 首頁</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* ── Hero ── */
    .hero {
      min-height: 520px;
      background: linear-gradient(135deg, #3d1520 0%, #6b2d3e 45%, #c26b7c 100%);
      display: flex; align-items: center;
      position: relative; overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .hero-content {
      max-width: var(--max-w); margin: 0 auto; padding: 80px 24px;
      position: relative; z-index: 1;
    }
    .hero-eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 12px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase;
      color: rgba(255,255,255,.7); margin-bottom: 20px;
    }
    .hero-eyebrow::before {
      content: ''; width: 24px; height: 2px;
      background: rgba(255,255,255,.5); border-radius: 2px;
    }
    .hero-title {
      font-size: clamp(2.2rem, 5vw, 3.5rem);
      font-weight: 700; color: white; line-height: 1.15;
      margin-bottom: 18px; letter-spacing: -.5px;
    }
    .hero-title em { color: #f9cfd8; font-style: normal; }
    .hero-desc {
      font-size: 16px; color: rgba(255,255,255,.75);
      margin-bottom: 36px; max-width: 480px; line-height: 1.7;
    }
    .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
    .hero-stats {
      display: flex; gap: 40px; margin-top: 48px;
      padding-top: 32px; border-top: 1px solid rgba(255,255,255,.12);
      padding-bottom: 32px;
    }
    .hero-stat-num { font-size: 1.75rem; font-weight: 700; color: white; }
    .hero-stat-label { font-size: 12px; color: rgba(255,255,255,.55); margin-top: 2px; }

    /* ── Feature Pills ── */
    .feature-wrap { max-width: var(--max-w); margin: 0 auto; padding: 0 24px; }
    .feature-row {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
      margin-top: -24px; position: relative; z-index: 2;
    }
    .feature-pill {
      background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border);
      padding: 20px 22px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 14px;
      transition: all var(--t); cursor: pointer; text-decoration: none; color: inherit;
    }
    .feature-pill:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: var(--rose-200); }
    .feature-pill-icon {
      width: 44px; height: 44px; border-radius: var(--r); flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 20px;
    }
    .feature-pill-title { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
    .feature-pill-desc { font-size: 12px; color: var(--text-3); }

    @media(max-width:768px){
      .feature-row { grid-template-columns: repeat(2,1fr); margin-top: -16px; }
      .hero-stats { gap: 24px; flex-wrap: wrap; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<!-- Hero -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-eyebrow">AI 驅動的美妝平台</div>
    <h1 class="hero-title">找到最適合<em>你</em>的<br>彩妝產品</h1>
    <p class="hero-desc">透過 AI 膚色分析，精準推薦適合你的彩妝。超過 <?= number_format($statProducts) ?> 款產品，讓你輕鬆比較、收藏、評分。</p>
    <div class="hero-actions">
      <a href="/SA/New-SA/AI/index.php" class="btn btn-primary btn-lg">✨ 立即 AI 分析</a>
      <a href="/SA/New-SA/產品/products.php" class="btn btn-ghost btn-lg">瀏覽產品</a>
    </div>
    <div class="hero-stats">
      <div><div class="hero-stat-num"><?= number_format($statProducts) ?>+</div><div class="hero-stat-label">精選產品</div></div>
      <div><div class="hero-stat-num"><?= number_format($statUsers) ?>+</div><div class="hero-stat-label">活躍會員</div></div>
      <div><div class="hero-stat-num"><?= number_format($statRatings) ?>+</div><div class="hero-stat-label">使用者評分</div></div>
    </div>
  </div>
</section>

<!-- Feature Pills -->
<div class="feature-wrap">
  <div class="feature-row">
    <a href="/SA/New-SA/AI/index.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#fce7ec;">🤖</div>
      <div><div class="feature-pill-title">AI 膚色分析</div><div class="feature-pill-desc">相機即時偵測膚色</div></div>
    </a>
    <a href="/SA/New-SA/產品/products.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#eff6ff;">💄</div>
      <div><div class="feature-pill-title">完整產品庫</div><div class="feature-pill-desc">搜尋篩選一秒找到</div></div>
    </a>
    <a href="/SA/New-SA/首頁/video.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#f5f3ff;">🎬</div>
      <div><div class="feature-pill-title">影片交流</div><div class="feature-pill-desc">分享彩妝教學影片</div></div>
    </a>
    <a href="/SA/New-SA/產品/compare.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#f0fdf4;">⚖️</div>
      <div><div class="feature-pill-title">產品比較</div><div class="feature-pill-desc">並排分析找出最優</div></div>
    </a>
  </div>
</div>

<!-- Products -->
<section class="section">
  <div class="section-inner">
    <div class="section-header">
      <div>
        <div class="section-eyebrow">熱門推薦</div>
        <div class="section-title">最新上架精選</div>
      </div>
      <a href="/SA/New-SA/產品/products.php" class="btn btn-outline btn-sm">查看全部 →</a>
    </div>

    <div class="product-grid">
    <?php while ($row = $result->fetch()): ?>
      <?php $isFav = in_array($row['p_id'], $favorites); ?>
      <div class="product-card">
        <div class="product-card-img">
          <?php if (!empty($row['image_url'])): ?>
            <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" onerror="this.parentElement.innerHTML='💄'">
          <?php else: ?>
            💄
          <?php endif; ?>
        </div>

        <?php if ($isFav): ?>
          <form action="remove_favorite.php" method="POST" style="position:absolute;top:10px;right:10px;margin:0;">
            <input type="hidden" name="id" value="<?= $row['p_id'] ?>">
            <button type="submit" class="fav-btn active" title="移除收藏">♥</button>
          </form>
        <?php else: ?>
          <form action="add_favorite.php" method="POST" style="position:absolute;top:10px;right:10px;margin:0;">
            <input type="hidden" name="id" value="<?= $row['p_id'] ?>">
            <button type="submit" class="fav-btn" title="加入收藏">♡</button>
          </form>
        <?php endif; ?>

        <div class="product-card-body">
          <div class="product-card-brand"><?= htmlspecialchars($row['brand']) ?></div>
          <div class="product-card-name"><?= htmlspecialchars($row['name']) ?></div>
          <?php if (!empty($row['category'])): ?>
            <span class="badge badge-rose"><?= htmlspecialchars($row['category']) ?></span>
          <?php endif; ?>
          <div class="product-card-actions">
            <a href="product.php?id=<?= $row['p_id'] ?>" class="btn btn-primary">查看</a>
            <form action="add_compare.php" method="POST" style="flex:1;">
              <input type="hidden" name="id" value="<?= $row['p_id'] ?>">
              <button type="submit" class="btn btn-outline" style="width:100%;">比較</button>
            </form>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
