<?php
session_start();
include __DIR__ . '/../db.php';

$ids = $_SESSION['favorite'] ?? [];
$result = null;

if (!empty($ids)) {
    $id_list = implode(",", array_map('intval', $ids));
    $result  = $conn->query("SELECT *, id AS p_id FROM data WHERE id IN ($id_list)");
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 我的收藏</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .fav-wrap { max-width: var(--max-w); margin: 36px auto 80px; padding: 0 24px; }
    .fav-header { margin-bottom: 24px; }
    .fav-header h2 { font-size: 1.4rem; font-weight: 700; }
    .fav-header p  { font-size: 13px; color: var(--text-3); margin-top: 4px; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<div class="fav-wrap">
  <?php if (empty($ids)): ?>
    <div class="empty">
      <div class="empty-icon">♡</div>
      <h3>目前沒有收藏</h3>
      <p>快去產品頁面加入喜歡的商品吧！</p>
      <a href="products.php" class="btn btn-primary">瀏覽產品</a>
    </div>
  <?php else: ?>
    <div class="fav-header">
      <h2>♥ 我的收藏</h2>
      <p>共 <?= count($ids) ?> 件商品</p>
    </div>

    <div class="product-grid">
    <?php while ($row = $result->fetch()): ?>
      <div class="product-card">
        <div class="product-card-img">
          <?php if (!empty($row['image_url'])): ?>
            <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" onerror="this.parentElement.innerHTML='💄'" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>💄<?php endif; ?>
        </div>

        <form action="remove_favorite.php" method="POST" style="position:absolute;top:10px;right:10px;margin:0;">
          <input type="hidden" name="id" value="<?= $row['p_id'] ?>">
          <button type="submit" class="fav-btn active" title="移除收藏">♥</button>
        </form>

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
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
