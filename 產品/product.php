<?php
session_start();
include 'db.php';

// 依分類決定評分屬性
$attributeMap = [
    '粉底'   => ['控油力', '延展性', '易上色', '持久度', '遮瑕力'],
    '底妝'   => ['控油力', '延展性', '易上色', '持久度', '遮瑕力'],
    '氣墊'   => ['控油力', '延展性', '易上色', '持久度', '遮瑕力'],
    '口紅'   => ['顯色度', '持久度', '滋潤度', '發色效果'],
    '唇釉'   => ['顯色度', '持久度', '滋潤度', '發色效果'],
    '唇膏'   => ['顯色度', '持久度', '滋潤度', '發色效果'],
    '眼影'   => ['顯色度', '延展性', '持久度', '不易暈染'],
    '眼線'   => ['顯色度', '持久度', '不易暈染', '易上色'],
    '睫毛膏' => ['拉長效果', '增量效果', '持久度', '不易暈染'],
    '腮紅'   => ['顯色度', '延展性', '持久度', '自然感'],
    '修容'   => ['顯色度', '延展性', '自然感', '易上色'],
    '打亮'   => ['顯色度', '自然感', '持久度', '易上色'],
    '護膚'   => ['保濕度', '吸收速度', '延展性', '滋潤度'],
];
$defaultAttributes = ['顯色度', '持久度', '易上色', '延展性'];
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=2">
    <title>產品詳情</title>
</head>

<body>

<?php include 'header.php'; ?>

<?php
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="products"><div class="empty-state"><h3>缺少產品編號</h3><p><a href="products.php">返回產品列表</a></p></div></div>';
    include 'footer.php';
    exit;
}

$sql = "SELECT *, id AS p_id FROM data WHERE id=$id";
$result = $conn->query($sql);
if (!$result) {
    echo '<div class="products"><div class="empty-state"><h3>查詢失敗</h3><p>產品資料表可能尚未匯入，或資料庫連線名稱不正確。</p><p><a href="products.php">返回產品列表</a></p></div></div>';
    include 'footer.php';
    exit;
}

$row = $result->fetch();

if(!$row){
    echo '<div class="products"><div class="empty-state"><h3>產品不存在</h3><p><a href="products.php">返回產品列表</a></p></div></div>';
    include 'footer.php';
    exit;
}

$favorites = $_SESSION['favorite'] ?? [];
$isFav = in_array($row['p_id'], $favorites);

// 累加觀看數（同一 session 同一產品只算一次）
$viewedKey = 'viewed_product_' . $id;
if (empty($_SESSION[$viewedKey])) {
    $_SESSION[$viewedKey] = true;
    try {
        $pdo->prepare("UPDATE data SET view_count = view_count + 1 WHERE id = ?")
            ->execute([$id]);
    } catch (Exception $e) { /* view_count 欄位尚未建立時跳過 */ }
}
?>

<div class="product-detail">
    <div style="margin-bottom: 20px;">
        <a href="javascript:history.back()" style="color: #efc6cd; text-decoration: none; font-size: 14px;">← 返回</a>
    </div>

    <div class="product-detail-grid">
        <div>
            <img src="images/<?php echo $row['p_id']; ?>.jpg" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 100%; border-radius: 15px;">
        </div>

        <div class="product-info">
            <h1><?php echo htmlspecialchars($row['name']); ?></h1>
            
            <p><strong>品牌：</strong><?php echo htmlspecialchars($row['brand']); ?></p>
            <p><strong>分類：</strong><?php echo htmlspecialchars($row['category']); ?></p>
            <p><strong>產地：</strong><?php echo htmlspecialchars($row['origin']); ?></p>

            <h3>用途</h3>
            <p><?php echo htmlspecialchars($row['purpose']); ?></p>

            <h3>成分</h3>
            <p><?php echo htmlspecialchars($row['ingredients']); ?></p>

            <h3>注意事項</h3>
            <p><?php echo htmlspecialchars($row['precautions']); ?></p>

            <div class="action-buttons">
                <?php if($isFav){ ?>
                    <form action="remove_favorite.php" method="POST" style="flex: 1;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">❤️ 取消收藏</button>
                    </form>
                <?php }else{ ?>
                    <form action="add_favorite.php" method="POST" style="flex: 1;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">🤍 加入收藏</button>
                    </form>
                <?php } ?>

                <form action="add_compare.php" method="POST" style="flex: 1;">
                    <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                    <button type="submit" class="btn btn-outline" style="width: 100%;">⚖️ 加入比較</button>
                </form>
            </div>
        </div>
    </div>

    <h3 style="margin-top: 40px; margin-bottom: 20px;">色號列表</h3>

    <?php
    $sql2 = "SELECT * FROM product_colors WHERE p_id=$id";
    $result2 = $conn->query($sql2);

    if (!$result2) {
        echo '<p style="color: #999;">色號資料查詢失敗，請確認 product_colors 資料表已匯入</p>';
        include 'footer.php';
        exit;
    }

    if($result2->rowCount() > 0){
    ?>
        <div class="colors-grid">
        <?php
        while($color = $result2->fetch()){
        ?>
            <div class="color-item">
                <div class="color-circle" style="background: <?php echo htmlspecialchars($color['color_hex']); ?>;"></div>
                <div class="color-name"><?php echo htmlspecialchars($color['color_name']); ?></div>
            </div>
        <?php } ?>
        </div>
    <?php }else{ ?>
        <p style="color: #999;">暫無色號資訊</p>
    <?php } ?>

<?php
// 決定評分屬性
$category   = $row['category'] ?? '';
$attributes = $attributeMap[$category] ?? $defaultAttributes;

// 查詢各屬性平均分
$avgRatings = [];
if (!empty($attributes)) {
    $placeholders = implode(',', array_fill(0, count($attributes), '?'));
    try {
        $stmtAvg = $pdo->prepare("
            SELECT attribute, ROUND(AVG(score)::numeric,1) AS avg_score, COUNT(*) AS total
            FROM product_ratings
            WHERE product_id = ? AND attribute IN ($placeholders)
            GROUP BY attribute
        ");
        $stmtAvg->execute(array_merge([$id], $attributes));
        foreach ($stmtAvg->fetchAll() as $r) {
            $avgRatings[$r['attribute']] = ['avg' => (float)$r['avg_score'], 'total' => (int)$r['total']];
        }
    } catch (Exception $e) { /* table may not exist yet */ }
}

// 查詢目前使用者的評分
$myRatings = [];
if (isset($_SESSION['user']) && !empty($attributes)) {
    $placeholders = implode(',', array_fill(0, count($attributes), '?'));
    try {
        $stmtMy = $pdo->prepare("
            SELECT attribute, score FROM product_ratings
            WHERE product_id = ? AND username = ? AND attribute IN ($placeholders)
        ");
        $stmtMy->execute(array_merge([$id, $_SESSION['user']], $attributes));
        foreach ($stmtMy->fetchAll() as $r) {
            $myRatings[$r['attribute']] = (int)$r['score'];
        }
    } catch (Exception $e) { /* table may not exist yet */ }
}
?>

<style>
.rating-section { margin-top: 40px; }
.rating-section h3 { margin-bottom: 20px; }
.rating-blocks { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media(max-width:600px){ .rating-blocks { grid-template-columns: 1fr; } }
.rating-block { background: #fff8f9; border-radius: 15px; padding: 20px; }
.rating-block h4 { margin: 0 0 16px; color: #c97b8a; font-size: 15px; }
.rating-row { display: flex; align-items: center; margin-bottom: 12px; gap: 10px; }
.rating-label { min-width: 72px; font-size: 13px; color: #555; }
.stars-display span, .stars-interactive span {
    font-size: 20px; cursor: default; color: #ddd; transition: color .15s;
}
.stars-display .filled { color: #f5a623; }
.stars-interactive span { cursor: pointer; }
.stars-interactive .filled { color: #f5a623; }
.stars-interactive .hovered { color: #f5a623; }
.rating-count { font-size: 11px; color: #aaa; margin-left: 4px; }
.rating-login-note { color: #aaa; font-size: 13px; margin-top: 8px; }
.rating-saved-msg { font-size: 12px; color: #27ae60; margin-left: 6px; opacity: 0; transition: opacity .4s; }
</style>

<div class="rating-section">
    <h3>商品評分</h3>
    <div class="rating-blocks">

        <!-- 平均評分 -->
        <div class="rating-block">
            <h4>使用者平均評價</h4>
            <?php foreach ($attributes as $attr): ?>
            <?php
                $avg   = $avgRatings[$attr]['avg']   ?? 0;
                $total = $avgRatings[$attr]['total']  ?? 0;
                $fullStars  = floor($avg);
                $halfStar   = ($avg - $fullStars >= 0.5) ? 1 : 0;
                $emptyStars = 5 - $fullStars - $halfStar;
            ?>
            <div class="rating-row">
                <span class="rating-label"><?php echo htmlspecialchars($attr); ?></span>
                <div class="stars-display">
                    <?php for($i=0;$i<$fullStars;$i++) echo '<span class="filled">★</span>'; ?>
                    <?php if($halfStar)                  echo '<span class="filled">☆</span>'; ?>
                    <?php for($i=0;$i<$emptyStars;$i++)  echo '<span>★</span>'; ?>
                </div>
                <span class="rating-count"><?php echo $total > 0 ? number_format($avg,1)." ($total人)" : '尚無評分'; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 使用者自評 -->
        <div class="rating-block">
            <h4>您的評價</h4>
            <?php if (isset($_SESSION['user'])): ?>
            <?php foreach ($attributes as $attr): ?>
            <?php $myScore = $myRatings[$attr] ?? 0; ?>
            <div class="rating-row">
                <span class="rating-label"><?php echo htmlspecialchars($attr); ?></span>
                <div class="stars-interactive" data-attr="<?php echo htmlspecialchars($attr); ?>" data-pid="<?php echo $id; ?>">
                    <?php for($i=1;$i<=5;$i++): ?>
                    <span data-val="<?php echo $i; ?>" class="<?php echo $i<=$myScore?'filled':''; ?>">★</span>
                    <?php endfor; ?>
                </div>
                <span class="rating-saved-msg" data-saved-attr="<?php echo htmlspecialchars($attr); ?>">已儲存</span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="rating-login-note"><a href="../首頁/login.php" style="color:#efc6cd;">登入</a> 後即可評分</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
document.querySelectorAll('.stars-interactive').forEach(function(container) {
    var stars = container.querySelectorAll('span');
    var attr  = container.dataset.attr;
    var pid   = container.dataset.pid;

    function setFilled(n) {
        stars.forEach(function(s, i) {
            s.classList.toggle('filled', i < n);
        });
    }

    stars.forEach(function(star) {
        star.addEventListener('mouseenter', function() {
            var v = parseInt(this.dataset.val);
            stars.forEach(function(s,i){ s.classList.toggle('hovered', i<v); });
        });
        star.addEventListener('mouseleave', function() {
            stars.forEach(function(s){ s.classList.remove('hovered'); });
        });
        star.addEventListener('click', function() {
            var score = parseInt(this.dataset.val);
            setFilled(score);
            fetch('rate_product.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({product_id: parseInt(pid), attribute: attr, score: score})
            })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var msgEl = container.parentNode.querySelector('[data-saved-attr]');
                    if (msgEl) {
                        msgEl.style.opacity = '1';
                        setTimeout(function(){ msgEl.style.opacity='0'; }, 1800);
                    }
                }
            });
        });
    });
});
</script>

</div>

<?php include 'footer.php'; ?>

</body>
</html>