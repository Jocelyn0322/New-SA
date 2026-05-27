<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../db.php';

$keyword      = $_GET['keyword']  ?? '';
$category     = $_GET['category'] ?? '';
$color_filter = $_GET['color']    ?? '';
$sort         = $_GET['sort']     ?? 'newest';
$page         = max(1, intval($_GET['page'] ?? 1));
$per_page     = 12;

$sql_base = "SELECT DISTINCT p.*, p.id AS p_id, p.image_url FROM data p";
$sql_count = "SELECT COUNT(DISTINCT p.id) AS total FROM data p";
$joins    = "";
$wheres   = [];

if ($keyword)      { $kw = strtolower($keyword); $wheres[] = "(LOWER(p.name) LIKE '%$kw%' OR LOWER(p.brand) LIKE '%$kw%')"; }
if ($category)     $wheres[] = "p.category='$category'";
if ($color_filter) { $joins .= " LEFT JOIN product_colors pc ON p.id = pc.p_id"; $wheres[] = "pc.color_name='$color_filter'"; }

$where_clause = !empty($wheres) ? " WHERE " . implode(" AND ", $wheres) : "";
$order = match($sort) {
  'oldest' => "ORDER BY p.id ASC",
  'brand'  => "ORDER BY p.brand ASC, p.name ASC",
  default  => "ORDER BY p.brand ASC, p.name ASC",
};

$total_row = $conn->query($sql_count . $joins . $where_clause)->fetch();
$total     = intval($total_row['total'] ?? 0);
$total_pages = max(1, ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$result = $conn->query($sql_base . $joins . $where_clause . " $order LIMIT $per_page OFFSET $offset");

$categories_result = $conn->query("SELECT category FROM (SELECT DISTINCT category FROM data) sub ORDER BY CASE category
    WHEN '底妝' THEN 1 WHEN '遮瑕' THEN 2
    WHEN '眼影' THEN 3 WHEN '眼線' THEN 4 WHEN '睫毛膏' THEN 5
    WHEN '腮紅' THEN 6 WHEN '修容' THEN 7 WHEN '打亮' THEN 8
    WHEN '唇彩' THEN 9
    WHEN '護膚' THEN 10 WHEN '護唇' THEN 11 WHEN '防曬' THEN 12
    ELSE 99 END");
$favorites = $_SESSION['favorite'] ?? [];
$compare_count = count($_SESSION['compare'] ?? []);

/* build pagination URL helper */
function page_url($p) {
  $q = $_GET;
  $q['page'] = $p;
  return '?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 產品</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .search-hero { background: var(--card); border-bottom: 1px solid var(--border); padding: 20px 0; position: sticky; top: var(--header-h); z-index: 50; }
    .search-hero-inner { max-width: var(--max-w); margin: 0 auto; padding: 0 24px; display: flex; gap: 10px; align-items: center; }
    .search-input-wrap { flex: 1; position: relative; }
    .search-input-wrap input { width: 100%; padding: 10px 14px 10px 40px; border: 1.5px solid var(--border); border-radius: var(--r); font-size: 14px; font-family: inherit; outline: none; transition: border var(--t); background: var(--bg); }
    .search-input-wrap input:focus { border-color: var(--rose); background: white; }
    .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-3); font-size: 15px; pointer-events: none; }
    .filter-wrap { max-width: var(--max-w); margin: 20px auto 0; padding: 0 24px; }
    .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }
    /* Mobile: horizontal scroll for filter pills */
    @media(max-width:640px){
      .filter-wrap { padding: 0; }
      .filter-pills { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 0 14px 8px; scrollbar-width: none; }
      .filter-pills::-webkit-scrollbar { display: none; }
      .filter-pill { flex-shrink: 0; }
    }
    .filter-pill { padding: 6px 16px; border-radius: var(--r-full); font-size: 13px; font-weight: 500; border: 1.5px solid var(--border); background: var(--card); color: var(--text-2); cursor: pointer; text-decoration: none; transition: all var(--t); }
    .filter-pill:hover { border-color: var(--rose); color: var(--rose); }
    .filter-pill.active { background: var(--rose); color: white; border-color: var(--rose); }
    .products-wrap { max-width: var(--max-w); margin: 24px auto 60px; padding: 0 24px; }
    .sort-select { padding: 9px 32px 9px 12px; border: 1.5px solid var(--border); border-radius: var(--r); font-size: 13px; font-weight: 600; font-family: inherit; color: var(--text-2); background: var(--card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239ca3af' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") no-repeat right 10px center; appearance: none; cursor: pointer; outline: none; transition: border var(--t); flex-shrink: 0; }
    .sort-select:focus { border-color: var(--rose); }
    .compare-float { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: var(--text); color: white; padding: 10px 24px; border-radius: var(--r-full); font-size: 13px; font-weight: 600; text-decoration: none; box-shadow: var(--shadow-lg); z-index: 200; display: flex; align-items: center; gap: 8px; }
    .fab-btn { position: fixed; bottom: 24px; right: 24px; background: var(--rose); color: white; border: none; border-radius: var(--r-full); padding: 13px 22px; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: var(--shadow-rose); z-index: 200; transition: all var(--t); }
    .fab-btn:hover { background: var(--rose-h); transform: translateY(-2px); }
    /* Modal共用 */
    .modal-ov { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-ov.active { display: flex; }
    .modal-box2 { background: var(--card); border-radius: var(--r-xl); padding: 28px; width: 90%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-lg); position: relative; }
    .modal-box2 h3 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
    .modal-box2 p.desc { font-size: 13px; color: var(--text-3); margin-bottom: 18px; }
    .modal-box2 label { display: block; font-size: 13px; font-weight: 600; color: var(--text-2); margin: 12px 0 4px; }
    .modal-box2 input, .modal-box2 select, .modal-box2 textarea { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: var(--r); font-size: 14px; font-family: inherit; outline: none; transition: border var(--t); box-sizing: border-box; }
    .modal-box2 input:focus, .modal-box2 select:focus, .modal-box2 textarea:focus { border-color: var(--rose); }
    .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
    .modal-msg { margin-top: 12px; font-size: 13px; text-align: center; min-height: 18px; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Mobile: search bar stacking ── */
    @media (max-width: 640px) {
      .search-hero-inner { flex-wrap: wrap; gap: 8px; padding: 0 12px; }
      .search-input-wrap { flex: 1 1 100%; order: -1; }
      .sort-select { flex: 1; min-width: 0; }
      .btn-img-search { display: none; }
      .filter-wrap { padding: 0 12px; }
      .products-wrap { padding: 0 12px; margin-top: 16px; }
    }
    @media (max-width: 480px) {
      .search-hero { padding: 14px 0; }
      .nav-tabs-scroll { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
    }

    .product-card-img { height: 220px; overflow: hidden; }
    .product-card-img img { width: 100%; height: 100%; object-fit: contain; background: #f5f5f5; padding: 8px; display: block; }
    .product-card-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      padding: 14px 16px 16px;
    }
    .product-card-brand {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .06em;
      color: var(--text-3);
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .product-card-name {
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      line-height: 1.8;
      margin-bottom: 8px;
    }
    /* badge + 色號 區域固定高度，無論有無都佔位 */
    .product-card-mid {
      min-height: 58px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      align-items: flex-start;
    }
    .badge {
      display: inline-block;
      padding: 3px 12px;
      border-radius: 99px;
      font-size: 12px;
      font-weight: 600;
    }
    .badge-rose {
      background: #fce7ec;
      color: #c26b7c;
    }
    .product-card-actions {
      display: flex;
      gap: 8px;
      margin-top: auto;
      padding-top: 12px;
      align-items: center;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<!-- Search Bar -->
<div class="search-hero">
  <div class="search-hero-inner">
    <form class="search-input-wrap" method="GET" id="searchForm">
      <span class="search-icon">🔍</span>
      <input type="text" name="keyword" placeholder="搜尋品牌、產品名稱..." value="<?= htmlspecialchars($keyword) ?>">
      <?php if ($category): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    </form>
    <select class="sort-select" onchange="applySort(this.value)">
      <option value="newest" <?= $sort==='newest'?'selected':'' ?>>最新上架</option>
      <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>最早上架</option>
      <option value="brand"  <?= $sort==='brand' ?'selected':'' ?>>品牌 A→Z</option>
    </select>
    <button type="button" onclick="openImgSearch()" class="btn btn-secondary btn-sm btn-img-search">📷 以圖搜尋</button>
  </div>
</div>

<!-- Filters -->
<div class="filter-wrap">
  <div class="filter-pills">
    <?php
    $base_params = array_filter(['keyword' => $keyword, 'sort' => $sort !== 'newest' ? $sort : '']);
    $all_href = 'products.php' . ($base_params ? '?' . http_build_query(array_filter($base_params)) : '');
    ?>
    <a class="filter-pill <?= !$category ? 'active' : '' ?>" href="<?= $all_href ?>">全部</a>
    <?php while ($cat = $categories_result->fetch()): ?>
      <?php
      $cat_params = array_filter(['category' => $cat['category'], 'keyword' => $keyword, 'sort' => $sort !== 'newest' ? $sort : '']);
      ?>
      <a class="filter-pill <?= $category === $cat['category'] ? 'active' : '' ?>"
         href="?<?= http_build_query(array_filter($cat_params)) ?>">
        <?= htmlspecialchars($cat['category']) ?>
      </a>
    <?php endwhile; ?>
  </div>
</div>

<!-- Products -->
<div class="products-wrap">
  <?php if ($result->rowCount() > 0): ?>
    <div class="product-grid">
    <?php while ($row = $result->fetch()): ?>
      <?php
        $isFav = in_array($row['p_id'], $favorites);
        $colors_q = $conn->query("SELECT color_hex, color_name FROM product_colors WHERE p_id={$row['p_id']} LIMIT 4");
        $colors = $colors_q->fetchAll();
      ?>
      <div class="product-card">
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
    <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="empty">
      <div class="empty-icon">🔍</div>
      <h3>找不到符合的產品</h3>
      <p>試試其他搜尋條件或分類</p>
      <a href="products.php" class="btn btn-primary">查看全部</a>
    </div>
  <?php endif; ?>

  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="<?= page_url($page-1) ?>" class="page-btn">←</a>
    <?php else: ?>
      <span class="page-btn" style="opacity:.35;cursor:default;">←</span>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($total_pages, $page + 2);
    if ($start > 1): ?>
      <a href="<?= page_url(1) ?>" class="page-btn">1</a>
      <?php if ($start > 2): ?><span class="page-btn" style="border:none;cursor:default;">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
      <a href="<?= page_url($i) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($end < $total_pages): ?>
      <?php if ($end < $total_pages-1): ?><span class="page-btn" style="border:none;cursor:default;">…</span><?php endif; ?>
      <a href="<?= page_url($total_pages) ?>" class="page-btn"><?= $total_pages ?></a>
    <?php endif; ?>

    <?php if ($page < $total_pages): ?>
      <a href="<?= page_url($page+1) ?>" class="page-btn">→</a>
    <?php else: ?>
      <span class="page-btn" style="opacity:.35;cursor:default;">→</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Compare float -->
<?php if ($compare_count > 0): ?>
  <a href="compare.php" class="compare-float">
    ⚖ 比較 (<?= $compare_count ?>)
    <?php if ($compare_count >= 5): ?>
      <span style="margin-left:8px;background:#fff3;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;">已達上限 5/5</span>
    <?php endif; ?>
  </a>
<?php endif; ?>
<?php if (!empty($_GET['compare_full'])): ?>
  <div id="compareFullToast" style="position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#c26b7c;color:#fff;padding:10px 22px;border-radius:99px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.18);z-index:9999;">
    比較清單已滿，最多可加入 5 個產品
  </div>
  <script>setTimeout(()=>document.getElementById('compareFullToast')?.remove(), 3000);</script>
<?php endif; ?>

<!-- Add product FAB -->
<?php if (isset($_SESSION['user'])): ?>
  <button class="fab-btn" onclick="openSubmitModal()">＋ 新增產品</button>

  <!-- Submit Product Modal -->
  <div id="submitModal" class="modal-ov" onclick="if(event.target===this)closeSubmitModal()">
    <div class="modal-box2">
      <h3>新增產品申請</h3>
      <p class="desc">填寫後由管理者審核，通過後將正式上架。</p>
      <label>產品名稱 <span style="color:var(--red)">*</span></label>
      <input type="text" id="sub_name" placeholder="例：超輕薄氣墊粉底">
      <label>品牌</label>
      <input type="text" id="sub_brand" placeholder="例：LANEIGE">
      <label>分類</label>
      <select id="sub_category">
        <option value="">請選擇分類</option>
        <option>底妝</option><option>遮瑕</option><option>眼影</option>
        <option>眼線</option><option>睫毛膏</option><option>腮紅</option>
        <option>修容</option><option>打亮</option><option>唇彩</option>
        <option>護膚</option><option>護唇</option><option>防曬</option>
      </select>
      <label>產品描述</label>
      <textarea id="sub_desc" rows="3" placeholder="簡單描述產品特色、適合膚質等"></textarea>
      <label>參考售價</label>
      <input type="text" id="sub_price" placeholder="例：NT$680">
      <label>購買連結</label>
      <input type="text" id="sub_link" placeholder="https://...（選填）">
      <div class="modal-actions">
        <button class="btn btn-outline" onclick="closeSubmitModal()">取消</button>
        <button class="btn btn-primary" onclick="submitProduct()">送出申請</button>
      </div>
      <p id="submitMsg" class="modal-msg"></p>
    </div>
  </div>

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
<?php endif; ?>

<!-- Image Search Modal -->
<div id="imgSearchModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div id="imgModalInner" style="background:var(--card);border-radius:var(--r-xl);padding:28px;width:min(520px,94vw);max-height:88vh;overflow-y:auto;position:relative;box-shadow:var(--shadow-lg);scroll-behavior:smooth;">
    <button onclick="closeImgSearch()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-3);">✕</button>
    <h3 style="margin:0 0 6px;">📷 以圖搜尋產品</h3>
    <p style="font-size:13px;color:var(--text-3);margin:0 0 18px;">上傳產品照片，AI 自動識別並找出相似商品</p>
    <div id="imgDropZone"
      onclick="document.getElementById('imgFileInput').click()"
      ondragover="event.preventDefault();this.style.borderColor='var(--rose)';this.style.background='var(--rose-50)'"
      ondragleave="this.style.borderColor='var(--border)';this.style.background='var(--bg)'"
      ondrop="handleImgDrop(event)"
      style="border:2px dashed var(--border);border-radius:var(--r-lg);background:var(--bg);padding:28px 20px;text-align:center;cursor:pointer;transition:all .2s;">
      <div id="imgDropZoneContent">
        <div style="font-size:36px;margin-bottom:8px;">🖼️</div>
        <p style="font-size:14px;font-weight:600;color:var(--rose);margin:0 0 4px;">點擊或拖曳圖片至此</p>
        <p style="font-size:12px;color:var(--text-3);margin:0;">支援 JPG、PNG、WEBP</p>
      </div>
      <img id="imgPreview" src="" alt="" style="display:none;max-width:100%;max-height:200px;border-radius:var(--r);object-fit:contain;">
    </div>
    <input type="file" id="imgFileInput" accept="image/*" style="display:none" onchange="handleImgFile(this.files[0])">
    <button id="imgAnalyzeBtn" onclick="runImgSearch()" disabled class="btn btn-primary" style="width:100%;margin-top:14px;opacity:.5;cursor:not-allowed;">開始搜尋</button>
    <div id="imgLoading" style="display:none;text-align:center;padding:20px 0;">
      <div style="display:inline-block;width:28px;height:28px;border:3px solid var(--rose-100);border-top-color:var(--rose);border-radius:50%;animation:spin .8s linear infinite;"></div>
      <p style="font-size:13px;color:var(--text-3);margin:10px 0 0;">AI 正在識別產品…</p>
    </div>
    <div id="imgParsedTags" style="display:none;margin-top:16px;">
      <p style="font-size:12px;color:var(--text-3);margin:0 0 6px;">識別結果：</p>
      <div id="imgTagsContainer" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
    </div>
    <div id="imgResults" style="display:none;margin-top:18px;">
      <p id="imgResultsTitle" style="font-size:13px;font-weight:600;color:var(--text);margin:0 0 12px;"></p>
      <div id="imgResultsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;"></div>
    </div>
    <div id="imgNoResult" style="display:none;text-align:center;padding:20px 0;color:var(--text-3);font-size:13px;">找不到相似產品，試試其他照片</div>
  </div>
</div>

<?php include 'footer.php'; ?>

<script>
/* ── Search reset page ── */
document.getElementById('searchForm').addEventListener('submit', function() {
  const url = new URL(window.location.href);
  this.querySelectorAll('input[name="page"]').forEach(el => el.remove());
});

/* ── Sort ── */
function applySort(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('sort', val);
  url.searchParams.delete('page');
  window.location.href = url.toString();
}

/* ── Image Search ── */
let imgBase64 = null, imgMime = 'image/jpeg';

function openImgSearch() { document.getElementById('imgSearchModal').style.display='flex'; resetImgSearch(); }
function closeImgSearch() { document.getElementById('imgSearchModal').style.display='none'; }
document.addEventListener('keydown', e => { if (e.key==='Escape') closeImgSearch(); });
document.getElementById('imgSearchModal').addEventListener('click', function(e){ if(e.target===this) closeImgSearch(); });

function resetImgSearch() {
  imgBase64=null;
  document.getElementById('imgPreview').style.display='none';
  document.getElementById('imgDropZoneContent').style.display='block';
  const btn=document.getElementById('imgAnalyzeBtn');
  btn.disabled=true; btn.style.opacity='.5'; btn.style.cursor='not-allowed';
  ['imgLoading','imgParsedTags','imgResults','imgNoResult'].forEach(id=>document.getElementById(id).style.display='none');
  document.getElementById('imgFileInput').value='';
}
function handleImgDrop(e) {
  e.preventDefault();
  document.getElementById('imgDropZone').style.borderColor='var(--border)';
  document.getElementById('imgDropZone').style.background='var(--bg)';
  const file=e.dataTransfer.files[0];
  if(file&&file.type.startsWith('image/')) handleImgFile(file);
}
function handleImgFile(file) {
  if(!file) return;
  imgMime=file.type||'image/jpeg';
  const reader=new FileReader();
  reader.onload=ev=>{
    const dataUrl=ev.target.result;
    imgBase64=dataUrl.split(',')[1];
    const preview=document.getElementById('imgPreview');
    preview.src=dataUrl; preview.style.display='block';
    document.getElementById('imgDropZoneContent').style.display='none';
    const btn=document.getElementById('imgAnalyzeBtn');
    btn.disabled=false; btn.style.opacity='1'; btn.style.cursor='pointer';
    ['imgResults','imgNoResult','imgParsedTags'].forEach(id=>document.getElementById(id).style.display='none');
  };
  reader.readAsDataURL(file);
}
async function runImgSearch() {
  if(!imgBase64) return;
  document.getElementById('imgLoading').style.display='block';
  ['imgResults','imgNoResult','imgParsedTags'].forEach(id=>document.getElementById(id).style.display='none');
  document.getElementById('imgAnalyzeBtn').disabled=true;
  try {
    const res=await fetch('image_search_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({imageBase64:imgBase64,mimeType:imgMime})});
    const data=await res.json();
    document.getElementById('imgLoading').style.display='none';
    document.getElementById('imgAnalyzeBtn').disabled=false;
    if(data.error){alert('搜尋失敗：'+data.error);return;}
    if(data.terms&&data.terms.length){
      document.getElementById('imgTagsContainer').innerHTML=data.terms.map(t=>`<span style="background:var(--rose-50);color:var(--rose);border:1px solid var(--rose-200);border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;">#${t}</span>`).join('');
      document.getElementById('imgParsedTags').style.display='block';
    }
    if(!data.products||data.products.length===0){document.getElementById('imgNoResult').style.display='block';return;}
    document.getElementById('imgResultsTitle').textContent=`找到 ${data.products.length} 個相關產品`;
    document.getElementById('imgResultsGrid').innerHTML=data.products.map(p=>{
      const img=p.image_url?`<img src="${p.image_url}" alt="${p.name}" style="width:100%;height:90px;object-fit:cover;border-radius:var(--r-sm);" onerror="this.parentElement.style.background='var(--rose-50)';this.remove()">`:`<div style="width:100%;height:90px;background:var(--rose-50);border-radius:var(--r-sm);"></div>`;
      return `<a href="product.php?id=${p.id}" style="text-decoration:none;color:inherit;display:block;border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;transition:box-shadow .15s;" onmouseover="this.style.boxShadow='var(--shadow)'" onmouseout="this.style.boxShadow='none'">${img}<div style="padding:8px;"><p style="font-size:12px;font-weight:600;margin:0 0 2px;line-height:1.3;">${p.name}</p><p style="font-size:11px;color:var(--text-3);margin:0;">${p.brand} · ${p.category}</p></div></a>`;
    }).join('');
    document.getElementById('imgResults').style.display='block';
    const scrollTarget=document.getElementById('imgParsedTags').style.display!=='none'?document.getElementById('imgParsedTags'):document.getElementById('imgResults');
    document.getElementById('imgModalInner').scrollTo({top:scrollTarget.offsetTop-16,behavior:'smooth'});
  } catch(err) {
    document.getElementById('imgLoading').style.display='none';
    document.getElementById('imgAnalyzeBtn').disabled=false;
    alert('網路錯誤，請稍後再試');
  }
}

/* ── Submit / Report Modals ── */
function openSubmitModal() { document.getElementById('submitModal').classList.add('active'); document.getElementById('submitMsg').textContent=''; }
function closeSubmitModal() { document.getElementById('submitModal').classList.remove('active'); }
function openReportModal(id,name) {
  document.getElementById('report_product_id').value=id;
  document.getElementById('reportProductName').textContent='產品：'+name;
  document.getElementById('report_type').value='';
  document.getElementById('report_desc').value='';
  document.getElementById('reportMsg').textContent='';
  document.getElementById('reportModal').classList.add('active');
}
function closeReportModal() { document.getElementById('reportModal').classList.remove('active'); }

async function submitProduct() {
  const name=document.getElementById('sub_name').value.trim();
  if(!name){const m=document.getElementById('submitMsg');m.style.color='var(--red)';m.textContent='請填寫產品名稱';return;}
  const payload={product_name:name,brand:document.getElementById('sub_brand').value.trim(),category:document.getElementById('sub_category').value,description:document.getElementById('sub_desc').value.trim(),price:document.getElementById('sub_price').value.trim(),purchase_link:document.getElementById('sub_link').value.trim()};
  try {
    const resp=await fetch('submit_product.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const result=await resp.json();
    const msg=document.getElementById('submitMsg');
    msg.style.color=result.success?'var(--green)':'var(--red)';
    msg.textContent=result.message;
    if(result.success){['sub_name','sub_brand','sub_desc','sub_price','sub_link'].forEach(id=>document.getElementById(id).value='');document.getElementById('sub_category').value='';setTimeout(closeSubmitModal,2000);}
  } catch(e){document.getElementById('submitMsg').textContent='網路錯誤，請稍後再試';}
}

async function submitReport() {
  const type=document.getElementById('report_type').value;
  if(!type){const m=document.getElementById('reportMsg');m.style.color='var(--red)';m.textContent='請選擇回報類型';return;}
  const payload={product_id:document.getElementById('report_product_id').value,report_type:type,description:document.getElementById('report_desc').value.trim()};
  try {
    const resp=await fetch('report_product.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const result=await resp.json();
    const msg=document.getElementById('reportMsg');
    msg.style.color=result.success?'var(--green)':'var(--red)';
    msg.textContent=result.message;
    if(result.success) setTimeout(closeReportModal,2000);
  } catch(e){document.getElementById('reportMsg').textContent='網路錯誤，請稍後再試';}
}
</script>
</body>
</html>
