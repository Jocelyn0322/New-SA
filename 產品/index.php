<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../db.php';

$sql = "SELECT *, id AS p_id FROM data ORDER BY created_at DESC LIMIT 8";
$products  = $conn->query($sql)->fetchAll();   // 一次取完，避免邊 fetch 邊查
$favorites = $_SESSION['favorite'] ?? [];

// 一條查詢取所有統計數字
$statsRow     = $pdo->query("SELECT
    (SELECT COUNT(*) FROM data) AS products,
    (SELECT COUNT(*) FROM users WHERE COALESCE(status,'active') = 'active') AS users,
    (SELECT COUNT(*) FROM product_ratings) AS ratings
")->fetch();
$statProducts = (int)($statsRow['products'] ?? 0);
$statUsers    = (int)($statsRow['users']    ?? 0);
$statRatings  = (int)($statsRow['ratings']  ?? 0);

// 一條查詢取所有產品顏色（解決 N+1）
$colorsMap = [];
if (!empty($products)) {
    $ids = implode(',', array_map('intval', array_column($products, 'p_id')));
    $colorRows = $conn->query("SELECT p_id, color_hex, color_name FROM product_colors WHERE p_id IN ($ids) ORDER BY id")->fetchAll();
    foreach ($colorRows as $c) {
        if (!isset($colorsMap[$c['p_id']]) || count($colorsMap[$c['p_id']]) < 4) {
            $colorsMap[$c['p_id']][] = $c;
        }
    }
}
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
      display: flex; flex-wrap: wrap; gap: 40px; margin-top: 48px;
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

    /* ── Product Cards (同 products.php) ── */
    .product-card-img { height: 220px; overflow: hidden; }
    .product-card-img img { width: 100%; height: 100%; object-fit: contain; background: #f5f5f5; padding: 8px; display: block; }
    .product-card-body { flex: 1; display: flex; flex-direction: column; padding: 14px 16px 16px; }
    .product-card-brand { font-size: 11px; font-weight: 700; letter-spacing: .06em; color: var(--text-3); text-transform: uppercase; margin-bottom: 4px; }
    .product-card-name { font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.8; margin-bottom: 8px; }
    .product-card-mid { min-height: 58px; display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
    .badge { display: inline-block; padding: 3px 12px; border-radius: 99px; font-size: 12px; font-weight: 600; }
    .badge-rose { background: #fce7ec; color: #c26b7c; }
    .product-card-actions { display: flex; gap: 8px; margin-top: auto; padding-top: 12px; align-items: center; }

    /* ── Modal (回報) ── */
    .modal-ov { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-ov.active { display: flex; }
    .modal-box2 { background: var(--card); border-radius: var(--r-xl); padding: 28px; width: 90%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-lg); position: relative; }
    .modal-box2 h3 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
    .modal-box2 p.desc { font-size: 13px; color: var(--text-3); margin-bottom: 18px; }
    .modal-box2 label { display: block; font-size: 13px; font-weight: 600; color: var(--text-2); margin: 12px 0 4px; }
    .modal-box2 select, .modal-box2 textarea { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: var(--r); font-size: 14px; font-family: inherit; outline: none; transition: border var(--t); box-sizing: border-box; }
    .modal-box2 select:focus, .modal-box2 textarea:focus { border-color: var(--rose); }
    .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
    .modal-msg { margin-top: 12px; font-size: 13px; text-align: center; min-height: 18px; }

    @media(max-width:768px){
      .feature-row { grid-template-columns: repeat(2,1fr); margin-top: -16px; }
      .hero-stats { gap: 20px; flex-wrap: wrap; }
      .hero-content { padding: 60px 20px 48px; }
      .hero-actions { gap: 10px; }
    }
    @media(max-width:480px){
      .hero-content { padding: 44px 16px 36px; }
      .hero-desc { font-size: 14px; margin-bottom: 24px; }
      .hero-stats { gap: 12px 20px; }
      .hero-stats > div { flex: 1 1 40%; }
      .hero-stat-num { font-size: 1.4rem; }
      .feature-row { grid-template-columns: repeat(2, 1fr); gap: 10px; }
      .feature-pill { padding: 14px 14px; gap: 10px; }
      .feature-pill-icon { width: 36px; height: 36px; font-size: 17px; }
      .feature-pill-title { font-size: 13px; }
      .feature-pill-desc { display: none; }
    }

    /* ── Hero entrance ── */
    @keyframes gradFlow {
      0%,100%{background-position:0% 50%}
      50%{background-position:100% 50%}
    }
    .hero {
      background: linear-gradient(135deg,#3d1520,#6b2d3e,#a04060,#c26b7c,#6b2d3e,#3d1520);
      background-size: 300% 300%;
      animation: gradFlow 14s ease infinite;
    }
    .hero-eyebrow { animation: fadeInUp .6s .05s ease both; }
    .hero-title   { animation: fadeInUp .7s .15s ease both; }
    .hero-desc    { animation: fadeInUp .7s .25s ease both; }
    .hero-actions { animation: fadeInUp .6s .35s ease both; }
    .hero-stats   { animation: fadeInUp .6s .45s ease both; }

    /* ── Feature pills stagger ── */
    .feature-pill { animation: fadeInUp .6s ease both; }
    .feature-pill:nth-child(1){animation-delay:.5s}
    .feature-pill:nth-child(2){animation-delay:.6s}
    .feature-pill:nth-child(3){animation-delay:.7s}
    .feature-pill:nth-child(4){animation-delay:.8s}

    @media (prefers-reduced-motion: reduce) {
      .hero { animation: none; }
      [class*="hero-"], .feature-pill { animation: none; opacity:1; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<!-- Hero -->
<section class="hero">
  <canvas class="fw-canvas" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;"></canvas>
  <div class="hero-content">
    <div class="hero-eyebrow">AI 驅動的美妝平台</div>
    <h1 class="hero-title">找到最適合<em>你</em>的<br>彩妝產品</h1>
    <p class="hero-desc">透過 AI 膚色分析，精準推薦適合你的彩妝。超過 <?= number_format($statProducts) ?> 款產品，讓你輕鬆比較、收藏、評分。</p>
    <div class="hero-actions">
      <a href="<?= BASE_URL ?>/AI/index.php" class="btn btn-primary btn-lg">✨ 立即 AI 分析</a>
      <a href="<?= BASE_URL ?>/產品/products.php" class="btn btn-ghost btn-lg">瀏覽產品</a>
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
    <a href="<?= BASE_URL ?>/AI/index.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#fce7ec;">🤖</div>
      <div><div class="feature-pill-title">AI 膚色分析</div><div class="feature-pill-desc">相機即時偵測膚色</div></div>
    </a>
    <a href="<?= BASE_URL ?>/產品/products.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#eff6ff;">💄</div>
      <div><div class="feature-pill-title">完整產品庫</div><div class="feature-pill-desc">搜尋篩選一秒找到</div></div>
    </a>
    <a href="<?= BASE_URL ?>/首頁/video.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#f5f3ff;">🎬</div>
      <div><div class="feature-pill-title">影片交流</div><div class="feature-pill-desc">分享彩妝教學影片</div></div>
    </a>
    <a href="<?= BASE_URL ?>/產品/compare.php" class="feature-pill">
      <div class="feature-pill-icon" style="background:#f0fdf4;">⚖️</div>
      <div><div class="feature-pill-title">產品比較</div><div class="feature-pill-desc">並排分析找出最優</div></div>
    </a>
  </div>
</div>

<!-- Products -->
<section class="section">
  <div class="section-inner">
    <div style="margin-bottom:24px;">
      <div style="font-size:12px;color:var(--rose);font-weight:600;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:4px;">熱門推薦</div>
      <div style="font-size:1.3rem;font-weight:700;white-space:nowrap;color:var(--text);">最新上架精選</div>
    </div>

    <div class="product-grid">
    <?php $cardIdx = 0; foreach ($products as $row):
      $isFav  = in_array($row['p_id'], $favorites);
      $colors = $colorsMap[$row['p_id']] ?? [];
      $cardIdx++;
    ?>
      <div class="product-card card-reveal sd-<?= min($cardIdx, 8) ?>">
        <div class="product-card-img">
          <?php if (!empty($row['image_url'])): ?>
            <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" onerror="this.parentElement.innerHTML='💄'" style="width:100%;height:100%;object-fit:contain;padding:8px;background:#f5f5f5;">
          <?php else: ?>💄<?php endif; ?>
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
          <div class="product-card-mid">
            <?php if (!empty($row['category'])): ?>
              <span class="badge badge-rose"><?= htmlspecialchars($row['category']) ?></span>
            <?php endif; ?>
            <?php if (!empty($colors)): ?>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <?php foreach ($colors as $c): ?>
                  <div style="width:18px;height:18px;background:<?= htmlspecialchars($c['color_hex']) ?>;border-radius:50%;border:1.5px solid rgba(0,0,0,.1);" title="<?= htmlspecialchars($c['color_name']) ?>"></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="product-card-actions">
            <a href="product.php?id=<?= $row['p_id'] ?>" class="btn btn-primary">查看</a>
            <form action="add_compare.php" method="POST" style="flex:1;">
              <input type="hidden" name="id" value="<?= $row['p_id'] ?>">
              <button type="submit" class="btn btn-outline" style="width:100%;">比較</button>
            </form>
            <?php if (isset($_SESSION['user'])): ?>
              <button type="button" class="btn btn-danger" style="padding:7px 10px;" onclick="openReportModal(<?= $row['p_id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">回報</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:32px;">
      <a href="<?= BASE_URL ?>/產品/products.php" class="btn btn-outline">查看全部 →</a>
    </div>
  </div>
</section>

<script src="<?= BASE_URL ?>/fireworks.js"></script>
<script>
/* 捲動顯示卡片 */
(function(){
  const cards = document.querySelectorAll('.card-reveal');
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
  }, { threshold: 0.08 });
  cards.forEach(c => io.observe(c));
})();
</script>

<?php if (isset($_SESSION['user'])): ?>
<!-- Report Modal -->
<div id="reportModal" class="modal-ov" onclick="if(event.target===this)closeReportModal()">
  <div class="modal-box2">
    <h3>回報產品狀況</h3>
    <p class="desc" id="reportProductName"></p>
    <input type="hidden" id="report_product_id">
    <label>回報類型 <span style="color:var(--red)">*</span></label>
    <select id="report_type">
      <option value="">請選擇回報類型</option>
      <option value="discontinued">產品已停產</option>
      <option value="new_version">已出新版本</option>
      <option value="wrong_info">資訊有誤</option>
      <option value="other">其他</option>
    </select>
    <label>補充說明</label>
    <textarea id="report_desc" rows="3" placeholder="請描述詳細狀況（選填）"></textarea>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeReportModal()">取消</button>
      <button class="btn btn-primary" onclick="submitReport()">送出回報</button>
    </div>
    <p id="reportMsg" class="modal-msg"></p>
  </div>
</div>
<script>
function openReportModal(id, name) {
  document.getElementById('report_product_id').value = id;
  document.getElementById('reportProductName').textContent = '產品：' + name;
  document.getElementById('report_type').value = '';
  document.getElementById('report_desc').value = '';
  document.getElementById('reportMsg').textContent = '';
  document.getElementById('reportModal').classList.add('active');
}
function closeReportModal() { document.getElementById('reportModal').classList.remove('active'); }
async function submitReport() {
  const type = document.getElementById('report_type').value;
  const msg = document.getElementById('reportMsg');
  if (!type) { msg.style.color = 'var(--red)'; msg.textContent = '請選擇回報類型'; return; }
  const payload = {
    product_id: document.getElementById('report_product_id').value,
    report_type: type,
    description: document.getElementById('report_desc').value.trim()
  };
  try {
    const resp = await fetch('report_product.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const result = await resp.json();
    msg.style.color = result.success ? 'var(--green)' : 'var(--red)';
    msg.textContent = result.message;
    if (result.success) setTimeout(closeReportModal, 2000);
  } catch(e) { msg.textContent = '網路錯誤，請稍後再試'; }
}
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
</body>
</html>
