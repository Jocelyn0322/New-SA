<?php
session_start();
include 'db.php';

$ids = $_SESSION['favorite'] ?? [];

if(empty($ids)){
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>我的收藏</title>
</head>
<body>
<?php include 'header.php'; ?>
    <div class="products">
        <div class="empty-state">
            <h3>❤️ 目前沒有收藏</h3>
            <p>快去產品頁面加入喜歡的商品吧！</p>
            <a href="products.php" class="btn btn-primary" style="padding: 10px 20px; margin-top: 15px; display: inline-block;">瀏覽產品</a>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
<?php
    exit;
}

$id_list = implode(",", array_map('intval', $ids));
$sql = "SELECT * FROM products WHERE p_id IN ($id_list)";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>我的收藏</title>
</head>

<body>

<?php include 'header.php'; ?>

<div class="products">
    <div style="max-width: 1400px; margin: 0 auto; margin-bottom: 30px;">
        <h2 style="color: #333; font-size: 26px; font-weight: 600;">❤️ 我的收藏</h2>
        <p style="color: #999; margin-top: 8px;">共 <?php echo count($ids); ?> 件商品</p>
    </div>

    <div class="product-grid">

    <?php 
    $favorites = $_SESSION['favorite'] ?? [];
    while($row = $result->fetch_assoc()){ 
        $isFav = in_array($row['p_id'], $favorites);
    ?>

        <div class="product-card">

            <div class="fav-btn">
                <form action="remove_favorite.php" method="POST" style="margin:0; padding:0;">
                    <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                    <button type="submit" class="heart active">❤️</button>
                </form>
            </div>

            <img src="images/<?php echo $row['p_id']; ?>.jpg" alt="<?php echo htmlspecialchars($row['name']); ?>">

            <div class="product-card-inner">
                <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                <p><?php echo htmlspecialchars($row['brand']); ?> - <?php echo htmlspecialchars($row['category']); ?></p>

                <div class="product-actions">
                    <a href="product.php?id=<?php echo $row['p_id']; ?>" class="btn btn-primary">查看詳細</a>
                    <form action="add_compare.php" method="POST" style="flex: 1;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="btn btn-outline">比較</button>
                    </form>
                </div>
            </div>

        </div>

    <?php } ?>

    </div>
</div>

<?php include 'footer.php'; ?>

</body>
</html>
