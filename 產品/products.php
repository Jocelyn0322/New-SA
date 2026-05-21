<?php 
session_start();
include 'db.php';

$keyword = $_GET['keyword'] ?? '';
$category = $_GET['category'] ?? '';
$color_filter = $_GET['color'] ?? '';

$sql = "SELECT DISTINCT p.*, p.id AS p_id, p.image_url FROM data p";

$joins = "";
$wheres = [];

if($keyword){
    $wheres[] = "(p.name LIKE '%$keyword%' OR p.brand LIKE '%$keyword%')";
}

if($category){
    $wheres[] = "p.category='$category'";
}

if($color_filter){
    $joins .= " LEFT JOIN product_colors pc ON p.id = pc.p_id";
    $wheres[] = "pc.color_name='$color_filter'";
}

$sql .= $joins;

if(!empty($wheres)){
    $sql .= " WHERE " . implode(" AND ", $wheres);
}

$result = $conn->query($sql);

// 取所有分類（用於篩選按鈕）
$categories_sql = "SELECT DISTINCT category FROM data";
$categories_result = $conn->query($categories_sql);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=2">
    <title>產品列表</title>
</head>

<body>

<?php include 'header.php'; ?>

<!-- 搜尋列 -->
<div class="search-section">
    <form class="search-bar">
        <input type="text" name="keyword" placeholder="搜尋產品名稱或品牌..." value="<?php echo htmlspecialchars($keyword); ?>">
        <button type="submit">🔍 搜尋</button>
    </form>
    <button type="button" id="imgSearchBtn" onclick="openImgSearch()"
        style="display:inline-flex;align-items:center;gap:6px;background:#fff;color:#c0748a;border:1.5px solid #e8b4c0;border-radius:20px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .2s;"
        onmouseover="this.style.background='#fdf0f3';this.style.borderColor='#c0748a'"
        onmouseout="this.style.background='#fff';this.style.borderColor='#e8b4c0'">
        📷 以圖搜尋
    </button>
</div>

<!-- 以圖搜尋 Modal -->
<div id="imgSearchModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;">
    <div id="imgModalInner" style="background:#fff;border-radius:20px;padding:28px;width:min(520px,94vw);max-height:88vh;overflow-y:auto;position:relative;box-shadow:0 8px 40px rgba(0,0,0,.18);scroll-behavior:smooth;">
        <!-- 關閉 -->
        <button onclick="closeImgSearch()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:#aaa;line-height:1;">✕</button>

        <h3 style="font-size:17px;font-weight:700;color:#3d2a30;margin:0 0 6px;">📷 以圖搜尋產品</h3>
        <p style="font-size:13px;color:#888;margin:0 0 18px;">上傳產品照片，AI 自動識別並找出相似商品</p>

        <!-- 上傳區 -->
        <div id="imgDropZone"
            onclick="document.getElementById('imgFileInput').click()"
            ondragover="event.preventDefault();this.style.borderColor='#c0748a';this.style.background='#fdf0f3'"
            ondragleave="this.style.borderColor='#e8c0cc';this.style.background='#fdf7f8'"
            ondrop="handleImgDrop(event)"
            style="border:2px dashed #e8c0cc;border-radius:14px;background:#fdf7f8;padding:28px 20px;text-align:center;cursor:pointer;transition:all .2s;">
            <div id="imgDropZoneContent">
                <div style="font-size:36px;margin-bottom:8px;">🖼️</div>
                <p style="font-size:14px;font-weight:600;color:#c0748a;margin:0 0 4px;">點擊或拖曳圖片至此</p>
                <p style="font-size:12px;color:#bbb;margin:0;">支援 JPG、PNG、WEBP</p>
            </div>
            <img id="imgPreview" src="" alt="" style="display:none;max-width:100%;max-height:200px;border-radius:10px;object-fit:contain;">
        </div>
        <input type="file" id="imgFileInput" accept="image/*" style="display:none" onchange="handleImgFile(this.files[0])">

        <!-- 分析按鈕 -->
        <button id="imgAnalyzeBtn" onclick="runImgSearch()" disabled
            style="width:100%;margin-top:14px;padding:11px;border-radius:12px;border:none;background:#e8b4c0;color:#fff;font-size:14px;font-weight:700;cursor:not-allowed;transition:all .2s;">
            開始搜尋
        </button>

        <!-- Loading -->
        <div id="imgLoading" style="display:none;text-align:center;padding:20px 0;">
            <div style="display:inline-block;width:28px;height:28px;border:3px solid #f0d0d8;border-top-color:#c0748a;border-radius:50%;animation:spin .8s linear infinite;"></div>
            <p style="font-size:13px;color:#888;margin:10px 0 0;">AI 正在識別產品…</p>
        </div>

        <!-- 識別結果標籤 -->
        <div id="imgParsedTags" style="display:none;margin-top:16px;">
            <p style="font-size:12px;color:#aaa;margin:0 0 6px;">識別結果：</p>
            <div id="imgTagsContainer" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
        </div>

        <!-- 搜尋結果 -->
        <div id="imgResults" style="display:none;margin-top:18px;">
            <p id="imgResultsTitle" style="font-size:13px;font-weight:600;color:#3d2a30;margin:0 0 12px;"></p>
            <div id="imgResultsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;"></div>
        </div>

        <!-- 無結果 -->
        <div id="imgNoResult" style="display:none;text-align:center;padding:20px 0;color:#aaa;font-size:13px;">
            找不到相似產品，試試其他照片
        </div>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
let imgBase64 = null;
let imgMime   = 'image/jpeg';

function openImgSearch() {
    const modal = document.getElementById('imgSearchModal');
    modal.style.display = 'flex';
    resetImgSearch();
}
function closeImgSearch() {
    document.getElementById('imgSearchModal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeImgSearch(); });
document.getElementById('imgSearchModal').addEventListener('click', function(e) {
    if (e.target === this) closeImgSearch();
});

function resetImgSearch() {
    imgBase64 = null;
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imgDropZoneContent').style.display = 'block';
    document.getElementById('imgAnalyzeBtn').disabled = true;
    document.getElementById('imgAnalyzeBtn').style.background = '#e8b4c0';
    document.getElementById('imgAnalyzeBtn').style.cursor = 'not-allowed';
    document.getElementById('imgLoading').style.display = 'none';
    document.getElementById('imgParsedTags').style.display = 'none';
    document.getElementById('imgResults').style.display = 'none';
    document.getElementById('imgNoResult').style.display = 'none';
    document.getElementById('imgFileInput').value = '';
}

function handleImgDrop(e) {
    e.preventDefault();
    document.getElementById('imgDropZone').style.borderColor = '#e8c0cc';
    document.getElementById('imgDropZone').style.background = '#fdf7f8';
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) handleImgFile(file);
}

function handleImgFile(file) {
    if (!file) return;
    imgMime = file.type || 'image/jpeg';
    const reader = new FileReader();
    reader.onload = ev => {
        const dataUrl = ev.target.result;
        imgBase64 = dataUrl.split(',')[1];
        const preview = document.getElementById('imgPreview');
        preview.src = dataUrl;
        preview.style.display = 'block';
        document.getElementById('imgDropZoneContent').style.display = 'none';
        const btn = document.getElementById('imgAnalyzeBtn');
        btn.disabled = false;
        btn.style.background = '#c0748a';
        btn.style.cursor = 'pointer';
        // clear previous results
        document.getElementById('imgResults').style.display = 'none';
        document.getElementById('imgNoResult').style.display = 'none';
        document.getElementById('imgParsedTags').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

async function runImgSearch() {
    if (!imgBase64) return;
    document.getElementById('imgLoading').style.display = 'block';
    document.getElementById('imgResults').style.display = 'none';
    document.getElementById('imgNoResult').style.display = 'none';
    document.getElementById('imgParsedTags').style.display = 'none';
    document.getElementById('imgAnalyzeBtn').disabled = true;

    try {
        const res = await fetch('image_search_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ imageBase64: imgBase64, mimeType: imgMime })
        });
        const data = await res.json();

        document.getElementById('imgLoading').style.display = 'none';
        document.getElementById('imgAnalyzeBtn').disabled = false;

        if (data.error) {
            alert('搜尋失敗：' + data.error);
            return;
        }

        // 顯示識別標籤
        if (data.terms && data.terms.length) {
            const container = document.getElementById('imgTagsContainer');
            container.innerHTML = data.terms.map(t =>
                `<span style="background:#fdf0f3;color:#c0748a;border:1px solid #e8c0cc;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;">#${t}</span>`
            ).join('');
            document.getElementById('imgParsedTags').style.display = 'block';
        }

        if (!data.products || data.products.length === 0) {
            document.getElementById('imgNoResult').style.display = 'block';
            const modalInner2 = document.getElementById('imgModalInner');
            modalInner2.scrollTo({ top: document.getElementById('imgNoResult').offsetTop - 16, behavior: 'smooth' });
            return;
        }

        // 顯示結果
        document.getElementById('imgResultsTitle').textContent = `找到 ${data.products.length} 個相關產品`;
        const grid = document.getElementById('imgResultsGrid');
        grid.innerHTML = data.products.map(p => {
            const img = p.image_url
                ? `<img src="${p.image_url}" alt="${p.name}" style="width:100%;height:90px;object-fit:cover;border-radius:8px;background:#f5f0f0;" onerror="this.style.background='#f5f0f0';this.removeAttribute('src')">`
                : `<div style="width:100%;height:90px;border-radius:8px;background:#f5f0f0;"></div>`;
            return `<a href="product.php?id=${p.id}" style="text-decoration:none;color:inherit;display:block;border:1px solid #f0e4e8;border-radius:12px;overflow:hidden;transition:box-shadow .15s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='none'">
                ${img}
                <div style="padding:8px;">
                    <p style="font-size:12px;font-weight:600;color:#3d2a30;margin:0 0 2px;line-height:1.3;">${p.name}</p>
                    <p style="font-size:11px;color:#aaa;margin:0;">${p.brand} · ${p.category}</p>
                </div>
            </a>`;
        }).join('');
        document.getElementById('imgResults').style.display = 'block';

        // 捲到識別標籤位置（在 modal 內部捲動）
        const modalInner = document.getElementById('imgModalInner');
        const scrollTarget = document.getElementById('imgParsedTags').style.display !== 'none'
            ? document.getElementById('imgParsedTags')
            : document.getElementById('imgResults');
        modalInner.scrollTo({ top: scrollTarget.offsetTop - 16, behavior: 'smooth' });

    } catch (err) {
        document.getElementById('imgLoading').style.display = 'none';
        document.getElementById('imgAnalyzeBtn').disabled = false;
        alert('網路錯誤，請稍後再試');
    }
}
</script>

<!-- 分類按鈕 -->
<div class="filter-section">
    <div class="filter-bar">
        <a class="filter-btn <?php echo !$category ? 'active' : ''; ?>" href="products.php">全部</a>
        <?php while($cat = $categories_result->fetch()){ ?>
            <a class="filter-btn <?php echo $category === $cat['category'] ? 'active' : ''; ?>" href="?category=<?php echo htmlspecialchars($cat['category']); ?>">
                <?php echo htmlspecialchars($cat['category']); ?>
            </a>
        <?php } ?>
    </div>
</div>


<div class="products">
    <div class="product-grid">

    <?php 
    if($result->rowCount() > 0){
        $favorites = $_SESSION['favorite'] ?? [];
        while($row = $result->fetch()){
            $isFav = in_array($row['p_id'], $favorites);
            
            // 取得這個產品的色號
            $product_colors = $conn->query("SELECT color_hex, color_name FROM product_colors WHERE p_id={$row['p_id']} LIMIT 4");
            $colors = [];
            while($c = $product_colors->fetch()){
                $colors[] = $c;
            }
    ?>

        <div class="product-card">

            <div class="fav-btn">
                <?php if($isFav){ ?>
                    <form action="remove_favorite.php" method="POST" style="margin:0; padding:0;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="heart active">❤️</button>
                    </form>
                <?php }else{ ?>
                    <form action="add_favorite.php" method="POST" style="margin:0; padding:0;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="heart">🤍</button>
                    </form>
                <?php } ?>
            </div>

            <?php $imgSrc = !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'images/' . $row['p_id'] . '.jpg'; ?>
            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>"
                 onerror="this.style.background='#f5f0f0';this.removeAttribute('src')">

            <div class="product-card-inner">
                <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                <p><?php echo htmlspecialchars($row['brand']); ?> - <?php echo htmlspecialchars($row['category']); ?></p>

                <!-- 色號預覽 -->
                <?php if(!empty($colors)){ ?>
                    <div style="margin: 8px 0; display: flex; gap: 4px; flex-wrap: wrap;">
                        <?php foreach($colors as $color){ ?>
                            <div style="width:20px; height:20px; background:<?php echo $color['color_hex']; ?>; border-radius:50%; border:1px solid #ddd;" title="<?php echo $color['color_name']; ?>"></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <div class="product-actions">
                    <a href="product.php?id=<?php echo $row['p_id']; ?>" class="btn btn-primary">查看詳細</a>
                    <form action="add_compare.php" method="POST" style="flex: 1;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="btn btn-outline">比較</button>
                    </form>
                    <?php if(isset($_SESSION['user'])): ?>
                    <button type="button" class="btn btn-report" onclick="openReportModal(<?php echo $row['p_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>')">回報</button>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    <?php 
        }
    }else{
        echo '<div class="empty-state" style="grid-column: 1/-1;"><h3>找不到符合的產品</h3><p>試試其他搜尋條件</p></div>';
    }
    ?>

    </div>
</div>

<!-- 浮動比較按鈕 -->
<?php
$compare_count = count($_SESSION['compare'] ?? []);
if($compare_count > 0){
?>
<a href="compare.php" class="compare-badge">
    ⚖️ 比較 (<?php echo $compare_count; ?>)
</a>
<?php } ?>

<?php if(isset($_SESSION['user'])): ?>
<!-- 浮動新增產品按鈕 -->
<button class="fab-submit-btn" onclick="openSubmitModal()">＋ 新增產品</button>

<!-- Modal：新增產品 -->
<div id="submitModal" class="user-modal-overlay" onclick="if(event.target===this)closeSubmitModal()">
    <div class="user-modal-box">
        <h3 class="user-modal-title">新增產品申請</h3>
        <p class="user-modal-desc">填寫後由管理者審核，通過後將正式上架。</p>
        <div class="user-modal-form">
            <label>產品名稱 <span style="color:#e05">*</span></label>
            <input type="text" id="sub_name" placeholder="例：超輕薄氣墊粉底">

            <label>品牌</label>
            <input type="text" id="sub_brand" placeholder="例：LANEIGE">

            <label>分類</label>
            <select id="sub_category">
                <option value="">請選擇分類</option>
                <option>粉底</option><option>口紅</option><option>眼影</option>
                <option>眼線</option><option>睫毛膏</option><option>腮紅</option>
                <option>修容</option><option>打亮</option><option>底妝</option>
                <option>唇釉</option><option>護膚</option><option>其他</option>
            </select>

            <label>產品描述</label>
            <textarea id="sub_desc" rows="3" placeholder="簡單描述產品特色、適合膚質等"></textarea>

            <label>參考售價</label>
            <input type="text" id="sub_price" placeholder="例：NT$680">

            <label>購買連結</label>
            <input type="text" id="sub_link" placeholder="https://...（選填）">
        </div>
        <div class="user-modal-actions">
            <button class="user-modal-cancel" onclick="closeSubmitModal()">取消</button>
            <button class="user-modal-submit" onclick="submitProduct()">送出申請</button>
        </div>
        <p id="submitMsg" class="user-modal-msg"></p>
    </div>
</div>

<!-- Modal：回報產品狀況 -->
<div id="reportModal" class="user-modal-overlay" onclick="if(event.target===this)closeReportModal()">
    <div class="user-modal-box">
        <h3 class="user-modal-title">回報產品狀況</h3>
        <p class="user-modal-desc" id="reportProductName"></p>
        <input type="hidden" id="report_product_id">
        <div class="user-modal-form">
            <label>回報類型 <span style="color:#e05">*</span></label>
            <select id="report_type">
                <option value="">請選擇回報類型</option>
                <option value="discontinued">產品已停產</option>
                <option value="new_version">已出新版本</option>
                <option value="wrong_info">資訊有誤</option>
                <option value="other">其他</option>
            </select>

            <label>補充說明</label>
            <textarea id="report_desc" rows="3" placeholder="請描述詳細狀況（選填）"></textarea>
        </div>
        <div class="user-modal-actions">
            <button class="user-modal-cancel" onclick="closeReportModal()">取消</button>
            <button class="user-modal-submit" onclick="submitReport()">送出回報</button>
        </div>
        <p id="reportMsg" class="user-modal-msg"></p>
    </div>
</div>
<?php endif; ?>

<style>
.btn-report {
    background: #fff0f3;
    color: #c0392b;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 12px;
    cursor: pointer;
    transition: background .2s;
}
.btn-report:hover { background: #ffe0e5; }

.fab-submit-btn {
    position: fixed;
    bottom: 28px;
    right: 28px;
    background: linear-gradient(135deg, #ff5a7e, #ff3a6f);
    color: #fff;
    border: none;
    border-radius: 28px;
    padding: 14px 24px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(255,90,126,.35);
    z-index: 900;
    transition: transform .2s, box-shadow .2s;
}
.fab-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(255,90,126,.45); }

.user-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.user-modal-overlay.active { display: flex; }

.user-modal-box {
    background: #fff;
    border-radius: 16px;
    padding: 32px 28px 24px;
    width: 90%;
    max-width: 480px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.user-modal-title { font-size: 20px; font-weight: 700; color: #333; margin-bottom: 6px; }
.user-modal-desc  { font-size: 13px; color: #888; margin-bottom: 18px; }

.user-modal-form label  { display: block; font-size: 13px; font-weight: 600; color: #555; margin: 12px 0 4px; }
.user-modal-form input,
.user-modal-form select,
.user-modal-form textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
    transition: border-color .2s;
}
.user-modal-form input:focus,
.user-modal-form select:focus,
.user-modal-form textarea:focus { outline: none; border-color: #ff5a7e; }

.user-modal-actions { display: flex; gap: 10px; margin-top: 20px; }
.user-modal-cancel {
    flex: 1; padding: 11px; border: 1.5px solid #ddd; border-radius: 8px;
    background: #f9f9f9; color: #555; font-weight: 600; cursor: pointer;
}
.user-modal-submit {
    flex: 2; padding: 11px; border: none; border-radius: 8px;
    background: linear-gradient(135deg, #ff5a7e, #ff3a6f); color: #fff;
    font-weight: 700; cursor: pointer;
}
.user-modal-submit:hover { opacity: .9; }
.user-modal-msg { margin-top: 12px; font-size: 13px; text-align: center; min-height: 18px; }
</style>

<script>
function openSubmitModal() {
    document.getElementById('submitModal').classList.add('active');
    document.getElementById('submitMsg').textContent = '';
}
function closeSubmitModal() {
    document.getElementById('submitModal').classList.remove('active');
}

function openReportModal(productId, productName) {
    document.getElementById('report_product_id').value = productId;
    document.getElementById('reportProductName').textContent = '產品：' + productName;
    document.getElementById('report_type').value = '';
    document.getElementById('report_desc').value = '';
    document.getElementById('reportMsg').textContent = '';
    document.getElementById('reportModal').classList.add('active');
}
function closeReportModal() {
    document.getElementById('reportModal').classList.remove('active');
}

async function submitProduct() {
    const name = document.getElementById('sub_name').value.trim();
    if (!name) { document.getElementById('submitMsg').style.color='#c0392b'; document.getElementById('submitMsg').textContent = '請填寫產品名稱'; return; }

    const payload = {
        product_name:  name,
        brand:         document.getElementById('sub_brand').value.trim(),
        category:      document.getElementById('sub_category').value,
        description:   document.getElementById('sub_desc').value.trim(),
        price:         document.getElementById('sub_price').value.trim(),
        purchase_link: document.getElementById('sub_link').value.trim(),
    };

    try {
        const resp   = await fetch('submit_product.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const result = await resp.json();
        const msg    = document.getElementById('submitMsg');
        msg.style.color = result.success ? '#27ae60' : '#c0392b';
        msg.textContent = result.message;
        if (result.success) {
            document.getElementById('sub_name').value = '';
            document.getElementById('sub_brand').value = '';
            document.getElementById('sub_desc').value = '';
            document.getElementById('sub_price').value = '';
            document.getElementById('sub_link').value = '';
            setTimeout(closeSubmitModal, 2000);
        }
    } catch(e) {
        document.getElementById('submitMsg').textContent = '網路錯誤，請稍後再試';
    }
}

async function submitReport() {
    const type = document.getElementById('report_type').value;
    if (!type) { document.getElementById('reportMsg').style.color='#c0392b'; document.getElementById('reportMsg').textContent = '請選擇回報類型'; return; }

    const payload = {
        product_id:  document.getElementById('report_product_id').value,
        report_type: type,
        description: document.getElementById('report_desc').value.trim(),
    };

    try {
        const resp   = await fetch('report_product.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const result = await resp.json();
        const msg    = document.getElementById('reportMsg');
        msg.style.color = result.success ? '#27ae60' : '#c0392b';
        msg.textContent = result.message;
        if (result.success) setTimeout(closeReportModal, 2000);
    } catch(e) {
        document.getElementById('reportMsg').textContent = '網路錯誤，請稍後再試';
    }
}
</script>

<?php include 'footer.php'; ?>

</body>
</html>