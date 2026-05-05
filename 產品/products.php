<?php 
session_start();
include 'db.php';

$keyword = $_GET['keyword'] ?? '';
$category = $_GET['category'] ?? '';
$color_filter = $_GET['color'] ?? '';

$sql = "SELECT DISTINCT p.* FROM products p";

$joins = "";
$wheres = [];

if($keyword){
    $wheres[] = "(p.name LIKE '%$keyword%' OR p.brand LIKE '%$keyword%')";
}

if($category){
    $wheres[] = "p.category='$category'";
}

if($color_filter){
    $joins .= " LEFT JOIN product_colors pc ON p.p_id = pc.p_id";
    $wheres[] = "pc.color_name='$color_filter'";
}

$sql .= $joins;

if(!empty($wheres)){
    $sql .= " WHERE " . implode(" AND ", $wheres);
}

$result = $conn->query($sql);

// 取所有分類（用於篩選按鈕）
$categories_sql = "SELECT DISTINCT category FROM products";
$categories_result = $conn->query($categories_sql);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
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
        <?php while($cat = $categories_result->fetch_assoc()){ ?>
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
        while($color = $colors->fetch_assoc()){
            echo '<a class="filter-btn" href="?color=' . $color['color_name'] . '">' . $color['color_name'] . '</a>';
        }
        ?>
    </div>
</div>

<div class="products">
    <div class="product-grid">

    <?php 
    if($result->num_rows > 0){
        $favorites = $_SESSION['favorite'] ?? [];
        while($row = $result->fetch_assoc()){
            $isFav = in_array($row['p_id'], $favorites);
            
            // 取得這個產品的色號
            $product_colors = $conn->query("SELECT color_hex, color_name FROM product_colors WHERE p_id={$row['p_id']} LIMIT 4");
            $colors = [];
            while($c = $product_colors->fetch_assoc()){
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

            <img src="images/<?php echo $row['p_id']; ?>.jpg" alt="<?php echo htmlspecialchars($row['name']); ?>">

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

<?php include 'footer.php'; ?>

</body>
</html>