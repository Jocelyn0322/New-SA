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
</div>

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

<!-- 在搜尋下方加 -->
<div class="filter-section">
    <div class="filter-bar">
        <a class="filter-btn" href="products.php">全部色號</a>

        <?php
        $colors = $conn->query("SELECT DISTINCT color_name FROM product_colors LIMIT 5");
        while($color = $colors->fetch()){
            echo '<a class="filter-btn" href="?color=' . $color['color_name'] . '">' . $color['color_name'] . '</a>';
        }
        ?>
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