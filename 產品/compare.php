<?php
require_once __DIR__ . '/../auth_check.php';
session_start();
include __DIR__ . '/../db.php';

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
    /* ── wrapper ── */
    .cmp-wrap {
      max-width: 1100px;
      margin: 36px auto 100px;
      padding: 0 24px;
    }

    /* ── page title bar ── */
    .cmp-titlebar {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
      flex-wrap: wrap;
    }
    .cmp-titlebar h2 {
      font-size: 1.45rem;
      font-weight: 800;
      color: var(--text);
      margin: 0;
    }
    .cmp-count-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 13px;
      border-radius: 99px;
      font-size: 12px;
      font-weight: 700;
      background: #f0eef8;
      color: #888;
      transition: background .2s, color .2s;
    }
    .cmp-count-badge.full {
      background: #fce7ec;
      color: #c26b7c;
    }
    .cmp-titlebar-right {
      margin-left: auto;
      display: flex;
      gap: 8px;
    }

    /* ── category label ── */
    .cmp-cat {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 16px;
      background: var(--rose-100);
      color: var(--rose);
      border-radius: 99px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 20px;
    }

    /* ── product card grid (sticky header) ── */
    .cmp-block { margin-bottom: 56px; }

    .cmp-table-wrap { overflow-x: auto; border-radius: 16px; box-shadow: 0 2px 20px rgba(0,0,0,.07); }

    .cmp-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      min-width: 520px;
    }

    /* product header cells */
    .cmp-table thead th {
      background: #fff;
      padding: 20px 16px 16px;
      text-align: center;
      vertical-align: top;
      border-bottom: 2px solid #f3f0fa;
    }
    .cmp-table thead th:first-child {
      background: #6b1e2e;
      width: 106px;
      border-right: none;
    }
    .cmp-table thead th + th {
      border-left: 1px solid #f3f0fa;
    }

    /* product image */
    .cmp-prod-img {
      width: 100%;
      height: 180px;
      object-fit: contain;
      border-radius: 12px;
      margin-bottom: 10px;
      background: #f7f5fc;
      padding: 6px;
    }
    .cmp-prod-img-placeholder {
      width: 100%;
      height: 180px;
      border-radius: 12px;
      background: linear-gradient(135deg,#f7f5fc,#f0edf8);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin-bottom: 10px;
    }

    /* ── clamp / expand ── */
    .cmp-clamp {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
      transition: all .25s;
    }
    .cmp-clamp.expanded {
      display: block;
      -webkit-line-clamp: unset;
      overflow: visible;
    }
    .cmp-more-btn {
      display: inline-block;
      margin-top: 6px;
      font-size: 11px;
      font-weight: 700;
      color: #c26b7c;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      text-decoration: underline;
      text-underline-offset: 2px;
    }
    .cmp-more-btn:hover { opacity: .75; }
    .cmp-prod-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--text);
      line-height: 1.4;
      margin-bottom: 4px;
    }
    .cmp-prod-brand {
      font-size: 11px;
      color: var(--text-3);
      margin-bottom: 10px;
    }
    .cmp-remove-btn {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 12px;
      border-radius: 99px;
      background: #fce7ec;
      color: #c26b7c;
      font-size: 11px;
      font-weight: 700;
      text-decoration: none;
      transition: background .15s;
    }
    .cmp-remove-btn:hover { background: #f8d0d8; }

    /* body rows */
    .cmp-table tbody tr { transition: background .12s; }
    .cmp-table tbody tr:hover { background: #fdf9fb; }
    .cmp-table tbody tr:nth-child(even) { background: #faf9fc; }
    .cmp-table tbody tr:nth-child(even):hover { background: #fdf9fb; }

    .cmp-table tbody td {
      padding: 14px 16px;
      font-size: 13px;
      color: var(--text);
      vertical-align: top;
      border-bottom: 1px solid #f3f0fa;
      text-align: center;
    }
    /* label column */
    .cmp-table tbody td:first-child {
      font-size: 15px;
      font-weight: 700;
      color: rgba(255,255,255,.9);
      background: #6b1e2e;
      text-align: center;
      white-space: nowrap;
      width: 106px;
      border-right: none;
      border-bottom: 1px solid rgba(255,255,255,.1);
      vertical-align: middle;
    }
    .cmp-table tbody td + td {
      border-left: 1px solid #f3f0fa;
    }

    /* color dots */
    .color-dots {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .color-dot {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 1.5px solid rgba(0,0,0,.1);
      flex-shrink: 0;
      cursor: default;
    }

    /* detail btn cell */
    .cmp-detail-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 18px;
      background: linear-gradient(135deg,#c26b7c,#9d2942);
      color: #fff;
      border-radius: 99px;
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
      transition: opacity .15s, transform .15s;
    }
    .cmp-detail-btn:hover { opacity: .88; transform: translateY(-1px); }

    /* last row no border */
    .cmp-table tbody tr:last-child td { border-bottom: none; }

    /* bottom actions */
    .cmp-actions {
      display: flex;
      gap: 10px;
      margin-top: 12px;
    }

    /* empty state */
    .cmp-empty {
      text-align: center;
      padding: 80px 24px;
    }
    .cmp-empty-icon {
      font-size: 3.5rem;
      margin-bottom: 16px;
      opacity: .35;
    }
    .cmp-empty h3 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 8px;
    }
    .cmp-empty p { color: var(--text-3); font-size: 14px; margin-bottom: 24px; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<div class="cmp-wrap">
<?php
$ids = $_SESSION['compare'] ?? [];
if (empty($ids)):
?>
  <div class="cmp-empty">
    <div class="cmp-empty-icon">⚖️</div>
    <h3>沒有產品可以比較</h3>
    <p>請先在產品頁面選擇要比較的商品</p>
    <a href="products.php" class="btn btn-primary">瀏覽產品</a>
  </div>
<?php else:
  $id_list = implode(',', array_map('intval', $ids));
  $result  = $conn->query("SELECT *, id AS p_id FROM data WHERE id IN ($id_list)");
  $grouped = [];
  while ($row = $result->fetch()) $grouped[$row['category']][] = $row;
  $total = count($ids);
?>

  <!-- title bar -->
  <div class="cmp-titlebar">
    <h2>⚖️ 產品比較</h2>
    <span class="cmp-count-badge <?= $total >= 5 ? 'full' : '' ?>">
      <?= $total ?> / 5<?= $total >= 5 ? '　已達上限' : '' ?>
    </span>
    <div class="cmp-titlebar-right">
      <a href="products.php" class="btn btn-outline btn-sm">← 返回產品</a>
      <a href="compare.php?clear=1" class="btn btn-danger btn-sm">清除全部</a>
    </div>
  </div>

  <?php foreach ($grouped as $category => $group): ?>
  <div class="cmp-block">

    <div class="cmp-cat">🏷 <?= htmlspecialchars($category) ?></div>

    <div class="cmp-table-wrap">
      <table class="cmp-table">
        <thead>
          <tr>
            <th style="font-size:15px;font-weight:700;color:rgba(255,255,255,.9);vertical-align:middle;">產品</th>
            <?php foreach ($group as $p): ?>
            <th>
              <?php if (!empty($p['image_url'])): ?>
                <img src="<?= htmlspecialchars($p['image_url']) ?>"
                     alt="<?= htmlspecialchars($p['name']) ?>"
                     class="cmp-prod-img"
                     onerror="this.style.display='none'">
              <?php else: ?>
                <div class="cmp-prod-img-placeholder">💄</div>
              <?php endif; ?>
              <div class="cmp-prod-name"><?= htmlspecialchars($p['name']) ?></div>
              <div class="cmp-prod-brand">
                <span style="background:#f0eef8;color:#9d2942;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;">
                  <?= htmlspecialchars($p['brand']) ?>
                </span>
              </div>
              <a href="compare.php?remove=<?= $p['p_id'] ?>" class="cmp-remove-btn">✕ 移除</a>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>

          <!-- 品牌 -->
          <tr>
            <td>品牌</td>
            <?php foreach ($group as $p): ?>
            <td><?= htmlspecialchars($p['brand']) ?></td>
            <?php endforeach; ?>
          </tr>

          <!-- 產地 -->
          <tr>
            <td>產地</td>
            <?php foreach ($group as $p): ?>
            <td><?= htmlspecialchars($p['origin'] ?? '—') ?></td>
            <?php endforeach; ?>
          </tr>

          <!-- 用途 -->
          <tr>
            <td>用途</td>
            <?php foreach ($group as $p): ?>
            <?php $purpose = htmlspecialchars($p['purpose'] ?? '—'); $needPurpose = mb_strlen($p['purpose'] ?? '') > 40; ?>
            <td style="text-align:left;line-height:1.6;color:var(--text-2);">
              <?php if ($needPurpose): ?>
                <span class="cmp-clamp"><?= $purpose ?></span>
                <button class="cmp-more-btn" onclick="toggleClamp(this)">查看更多</button>
              <?php else: ?>
                <?= $purpose ?>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>

          <!-- 成分 -->
          <tr>
            <td>成分</td>
            <?php foreach ($group as $p): ?>
            <?php $ingr = htmlspecialchars($p['ingredients'] ?? '—'); $needIngr = mb_strlen($p['ingredients'] ?? '') > 40; ?>
            <td style="text-align:left;font-size:12px;line-height:1.7;color:var(--text-3);">
              <?php if ($needIngr): ?>
                <span class="cmp-clamp"><?= $ingr ?></span>
                <button class="cmp-more-btn" onclick="toggleClamp(this)">查看更多</button>
              <?php else: ?>
                <?= $ingr ?>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>

          <!-- 色號 -->
          <tr>
            <td>色號</td>
            <?php foreach ($group as $p): ?>
            <td>
              <?php
              $colors = $conn->query("SELECT color_hex, color_name FROM product_colors WHERE p_id={$p['p_id']} LIMIT 8");
              if ($colors->rowCount() > 0):
              ?>
                <div class="color-dots">
                  <?php while ($c = $colors->fetch()): ?>
                    <div class="color-dot"
                         style="background:<?= htmlspecialchars($c['color_hex']) ?>;"
                         title="<?= htmlspecialchars($c['color_name']) ?>"></div>
                  <?php endwhile; ?>
                </div>
              <?php else: ?>
                <span style="color:var(--text-3);font-size:12px;">無色號</span>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>

          <!-- 詳細 -->
          <tr>
            <td>詳細</td>
            <?php foreach ($group as $p): ?>
            <td>
              <a href="product.php?id=<?= $p['p_id'] ?>" class="cmp-detail-btn">查看詳細 →</a>
            </td>
            <?php endforeach; ?>
          </tr>

        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>

<?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script>
function toggleClamp(btn) {
  const span = btn.previousElementSibling;
  const expanded = span.classList.toggle('expanded');
  btn.textContent = expanded ? '收合' : '查看更多';
}
</script>
</body>
</html>
