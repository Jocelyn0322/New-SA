<?php
session_start();
include 'db.php';

$sql = "SELECT *, id AS p_id FROM data ORDER BY created_at DESC LIMIT 6";
$result = $conn->query($sql);
$favorites = $_SESSION['favorite'] ?? [];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 首頁</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Hero */
    .hero {
      background: linear-gradient(135deg, #1a0820 0%, #3d1a4a 40%, #7a3060 70%, #c26b7c 100%);
      padding: 72px 0 80px; color: white; text-align: center;
    }
    .hero-inner { max-width: var(--max-w); margin: 0 auto; padding: 0 24px; }
    .hero-eyebrow { font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; opacity: .65; margin-bottom: 16px; }
    .hero h1 { font-size: clamp(2rem, 5vw, 3rem); font-weight: 700; line-height: 1.2; margin-bottom: 16px; }
    .hero p { font-size: 16px; opacity: .75; max-width: 480px; margin: 0 auto 32px; }
    .hero-pills { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-bottom: 36px; }
    .hero-pill {
      display: flex; align-items: center; gap: 8px; padding: 8px 18px;
      background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22);
      border-radius: var(--r-full); font-size: 13px; font-weight: 500; color: white;
      backdrop-filter: blur(4px); transition: background var(--t);
    }
    .hero-pill:hover { background: rgba(255,255,255,.22); }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- Hero -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">你的彩妝顧問</div>
    <h1>找到最適合你的<br>彩妝產品</h1>
    <p>透過 AI 分析膚色與膚質，精準推薦最適合你的彩妝品</p>
    <div class="hero-pills">
      <a href="/SA/New-SA/AI/index.php"      class="hero-pill">✨ AI 膚色分析</a>
      <a href="/SA/New-SA/產品/products.php" class="hero-pill">💄 產品庫</a>
      <a href="/SA/New-SA/首頁/video.php"    class="hero-pill">🎬 影片交流</a>
      <a href="/SA/New-SA/產品/compare.php"  class="hero-pill">⚖ 比較功能</a>
    </div>
    <a href="/SA/New-SA/AI/index.php" class="btn btn-ghost btn-lg">開始 AI 分析 →</a>
  </div>
</section>

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

        <!-- Favorite button (form POST) -->
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
