<?php
session_start();
include 'db.php';

if (isset($_GET['remove'])) {
    $removeId = intval($_GET['remove']);
    $_SESSION['compare'] = array_values(array_filter($_SESSION['compare'] ?? [], fn($id) => intval($id) !== $removeId));
    header('Location: compare.php'); exit;
}
if (isset($_GET['clear'])) {
    $_SESSION['compare'] = [];
    header('Location: products.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 產品比較</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .compare-wrap { max-width: var(--max-w); margin: 36px auto 80px; padding: 0 24px; }
    .compare-wrap h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 28px; }
    .compare-cat-label { display: inline-flex; align-items: center; padding: 4px 14px; background: var(--rose-100); color: var(--rose); border-radius: var(--r-full); font-size: 13px; font-weight: 700; margin-bottom: 14px; }
    .compare-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .compare-table th, .compare-table td { border: 1px solid var(--border); padding: 12px 14px; vertical-align: top; font-size: 13px; }
    .compare-table thead th { background: var(--rose-50); font-weight: 700; color: var(--rose); text-align: center; }
    .compare-table tbody tr:nth-child(even) { background: var(--bg); }
    .compare-table tbody td:first-child { font-weight: 600; color: var(--text-3); width: 110px; background: var(--bg); white-space: nowrap; }
    .compare-table td { text-align: center; }
    .compare-table td:first-child { text-align: left; }
    .compare-product-img { width: 100%; max-height: 130px; object-fit: cover; border-radius: var(--r); margin-bottom: 8px; }
    .compare-block { margin-bottom: 48px; }
    .compare-actions { display: flex; gap: 10px; margin-top: 28px; }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="compare-wrap">
  <?php
  $ids = $_SESSION['compare'] ?? [];
  if (empty($ids)):
  ?>
    <div class="empty">
      <div class="empty-icon">⚖</div>
      <h3>沒有產品可以比較</h3>
      <p>請先在產品頁面選擇要比較的商品</p>
      <a href="products.php" class="btn btn-primary">瀏覽產品</a>
    </div>
  <?php else: ?>
    <h2>⚖ 產品比較</h2>
    <?php
    $id_list = implode(',', array_map('intval', $ids));
    $result  = $conn->query("SELECT *, id AS p_id FROM data WHERE id IN ($id_list)");
    $grouped = [];
    while ($row = $result->fetch()) $grouped[$row['category']][] = $row;
    ?>

    <?php foreach ($grouped as $category => $group): ?>
      <div class="compare-block">
        <div class="compare-cat-label"><?= htmlspecialchars($category) ?></div>
        <div style="overflow-x:auto;">
          <table class="compare-table">
            <thead>
              <tr>
                <th style="width:110px;">商品資訊</th>
                <?php foreach ($group as $p): ?>
                  <th>
                    <?php if (!empty($p['image_url'])): ?>
                      <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="compare-product-img" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($p['name']) ?></div>
                    <a href="compare.php?remove=<?= $p['p_id'] ?>" class="btn btn-danger btn-sm" style="margin-top:6px;font-size:11px;">✕ 移除</a>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <tr><td>品牌</td><?php foreach ($group as $p): ?><td><?= htmlspecialchars($p['brand']) ?></td><?php endforeach; ?></tr>
              <tr><td>產地</td><?php foreach ($group as $p): ?><td><?= htmlspecialchars($p['origin'] ?? '—') ?></td><?php endforeach; ?></tr>
              <tr><td>用途</td><?php foreach ($group as $p): ?><td style="text-align:left;font-size:12px;"><?= htmlspecialchars($p['purpose'] ?? '—') ?></td><?php endforeach; ?></tr>
              <tr><td>成分</td><?php foreach ($group as $p): ?><td style="text-align:left;font-size:12px;color:var(--text-2);"><?= htmlspecialchars($p['ingredients'] ?? '—') ?></td><?php endforeach; ?></tr>
              <tr>
                <td>色號</td>
                <?php foreach ($group as $p): ?>
                  <td>
                    <?php
                    $colors = $conn->query("SELECT color_hex, color_name FROM product_colors WHERE p_id={$p['p_id']} LIMIT 6");
                    if ($colors->rowCount() > 0):
                    ?>
                      <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:center;">
                        <?php while ($c = $colors->fetch()): ?>
                          <div style="width:20px;height:20px;background:<?= htmlspecialchars($c['color_hex']) ?>;border-radius:50%;border:1.5px solid rgba(0,0,0,.1);" title="<?= htmlspecialchars($c['color_name']) ?>"></div>
                        <?php endwhile; ?>
                      </div>
                    <?php else: ?>
                      <span style="color:var(--text-3);font-size:12px;">無色號</span>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
              <tr>
                <td>詳細</td>
                <?php foreach ($group as $p): ?>
                  <td><a href="product.php?id=<?= $p['p_id'] ?>" class="btn btn-primary btn-sm">查看詳細</a></td>
                <?php endforeach; ?>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="compare-actions">
      <a href="products.php" class="btn btn-outline">← 返回產品</a>
      <a href="compare.php?clear=1" class="btn btn-danger" style="flex:1;justify-content:center;">清除全部比較</a>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
