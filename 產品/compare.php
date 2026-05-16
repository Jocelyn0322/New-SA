<?php
session_start();
include 'db.php';

if (isset($_GET['clear'])) {
    unset($_SESSION['compare']);
    header('Location: compare.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>比較頁面</title>
</head>

<body>

<?php include 'header.php'; ?>

<?php
$ids = $_SESSION['compare'] ?? [];

if (empty($ids)) {
    echo "<div class='products'><div class='empty-state'><h3>沒有產品可以比較</h3><p><a href='products.php'>返回產品列表</a></p></div></div>";
    include 'footer.php';
    exit;
}

$id_list = implode(",", array_map('intval', $ids));
$sql = "SELECT * FROM products WHERE p_id IN ($id_list)";
$result = $conn->query($sql);

$products = [];
while($row = $result->fetch()){
    $products[] = $row;
}
?>

<div class="compare-section">
    <div style="max-width: 100%; margin: 0 auto;">
        <h2 style="margin-bottom: 25px; color: #333; font-size: 24px;">⚖️ 產品比較</h2>

        <table class="compare-table">
            <thead>
                <tr>
                    <th style="width: 150px;">商品資訊</th>
                    <?php foreach($products as $p){ ?>
                        <th><?php echo htmlspecialchars($p['name']); ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>品牌</strong></td>
                    <?php foreach($products as $p){ ?>
                        <td><?php echo htmlspecialchars($p['brand']); ?></td>
                    <?php } ?>
                </tr>
                <tr>
                    <td><strong>分類</strong></td>
                    <?php foreach($products as $p){ ?>
                        <td><?php echo htmlspecialchars($p['category']); ?></td>
                    <?php } ?>
                </tr>
                <tr>
                    <td><strong>產地</strong></td>
                    <?php foreach($products as $p){ ?>
                        <td><?php echo htmlspecialchars($p['origin']); ?></td>
                    <?php } ?>
                </tr>
                <tr>
                    <td><strong>用途</strong></td>
                    <?php foreach($products as $p){ ?>
                        <td style="font-size: 13px;"><?php echo htmlspecialchars($p['purpose']); ?></td>
                    <?php } ?>
                </tr>
                <tr>
                    <td><strong>可用色號</strong></td>
                    <?php foreach($products as $p){ ?>
                        <td>
                            <?php
                            $colors = $conn->query("SELECT color_hex, color_name FROM product_colors WHERE p_id={$p['p_id']} LIMIT 5");
                            if($colors->rowCount() > 0){
                                echo '<div style="display: flex; gap: 6px; flex-wrap: wrap;">';
                                while($c = $colors->fetch()){
                                    echo '<div style="width:24px; height:24px; background:'.$c['color_hex'].'; border-radius:50%; border:1px solid #ddd; display: inline-block;" title="'.$c['color_name'].'"></div>';
                                }
                                echo '</div>';
                            }else{
                                echo '<span style="color: #999; font-size: 12px;">無色號資訊</span>';
                            }
                            ?>
                        </td>
                    <?php } ?>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 25px; display: flex; gap: 12px;">
            <a href="products.php" class="btn btn-outline" style="padding: 12px 20px; display: inline-block;">← 返回產品</a>
            <a href="compare.php?clear=1" class="btn btn-outline" style="padding: 12px 20px; display: inline-block; flex: 1; text-align: center;">清除全部比較</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

</body>
</html>