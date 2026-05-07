<?php 
session_start();
include 'db.php'; 
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>產品詳情</title>
</head>

<body>

<?php include 'header.php'; ?>

<?php
$id = intval($_GET['id']);

$sql = "SELECT * FROM products WHERE p_id=$id";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

if(!$row){
    echo '<div class="products"><div class="empty-state"><h3>產品不存在</h3><p><a href="products.php">返回產品列表</a></p></div></div>';
    include 'footer.php';
    exit;
}

$favorites = $_SESSION['favorite'] ?? [];
$isFav = in_array($row['p_id'], $favorites);
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

    if($result2->num_rows > 0){
    ?>
        <div class="colors-grid">
        <?php
        while($color = $result2->fetch_assoc()){
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

</div>

<?php include 'footer.php'; ?>

</body>
</html>