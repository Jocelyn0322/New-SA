<?php
session_start();
include 'db.php';

// 清除單一產品
if (isset($_GET['remove'])) {
    $removeId = intval($_GET['remove']);
    $_SESSION['compare'] = array_values(array_filter(
        $_SESSION['compare'] ?? [],
        fn($id) => intval($id) !== $removeId
    ));
    header('Location: compare.php');
    exit;
}

// 清除全部
if (isset($_GET['clear'])) {
    $_SESSION['compare'] = [];
    header('Location: products.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=2">
    <title>比較頁面</title>
    <style>
    .compare-category-block { margin-bottom: 48px; }
    .compare-category-label {
        display: inline-block;
        background: linear-gradient(135deg, #ff5a7e, #ff3a6f);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        padding: 6px 18px;
        border-radius: 20px;
        margin-bottom: 16px;
    }
    .compare-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .compare-table th, .compare-table td {
        border: 1px solid #f0d5dc;
        padding: 12px 14px;
        vertical-align: top;
        font-size: 14px;
    }
    .compare-table thead th {
        background: #fff0f3;
        font-weight: 700;
        color: #c97b8a;
        text-align: center;
    }
    .compare-table tbody tr:nth-child(even) { background: #fffafa; }
    .compare-table tbody td:first-child {
        font-weight: 600;
        color: #888;
        width: 120px;
        background: #fff8f9;
        white-space: nowrap;
    }
    .compare-table td { text-align: center; }
    .compare-table td:first-child { text-align: left; }
    .compare-product-img {
        width: 100%;
        max-height: 140px;
        object-fit: cover;
        border-radius: 10px;
        margin-bottom: 8px;
    }
    .compare-remove-btn {
        font-size: 11px;
        color: #e05;
        background: none;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        padding: 2px 8px;
        cursor: pointer;
        margin-top: 6px;
    }
    .compare-remove-btn:hover { background: #fff0f3; }
    .compare-actions { margin-top: 32px; display: flex; gap: 12px; }
    </style>
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

$id_list = implode(',', array_map('intval', $ids));
$result  = $conn->query("SELECT *, id AS p_id FROM data WHERE id IN ($id_list)");

// 依分類分群，保留加入順序
$grouped = [];
while ($row = $result->fetch()) {
    $grouped[$row['category']][] = $row;
}
?>

<div class="compare-section">
    <div style="max-width:100%; margin:0 auto;">
        <h2 style="margin-bottom:28px; color:#333; font-size:24px;">⚖️ 產品比較</h2>

        <?php foreach ($grouped as $category => $group): ?>
        <div class="compare-category-block">
            <div class="compare-category-label"><?php echo htmlspecialchars($category); ?></div>

            <div style="overflow-x:auto;">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th style="width:120px;">商品資訊</th>
                        <?php foreach ($group as $p): ?>
                        <th>
                            <?php $imgSrc = !empty($p['image_url']) ? htmlspecialchars($p['image_url']) : 'images/' . $p['p_id'] . '.jpg'; ?>
                            <img src="<?php echo $imgSrc; ?>"
                                 alt="<?php echo htmlspecialchars($p['name']); ?>"
                                 class="compare-product-img"
                                 onerror="this.style.display='none'">
                            <div><?php echo htmlspecialchars($p['name']); ?></div>
                            <a href="compare.php?remove=<?php echo $p['p_id']; ?>">
                                <button class="compare-remove-btn">✕ 移除</button>
                            </a>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>品牌</strong></td>
                        <?php foreach ($group as $p): ?>
                            <td><?php echo htmlspecialchars($p['brand']); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>產地</strong></td>
                        <?php foreach ($group as $p): ?>
                            <td><?php echo htmlspecialchars($p['origin']); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>用途</strong></td>
                        <?php foreach ($group as $p): ?>
                            <td style="font-size:13px; text-align:left;"><?php echo htmlspecialchars($p['purpose']); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>成分</strong></td>
                        <?php foreach ($group as $p): ?>
                            <td style="font-size:12px; text-align:left; color:#666;"><?php echo htmlspecialchars($p['ingredients']); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>可用色號</strong></td>
                        <?php foreach ($group as $p): ?>
                        <td>
                            <?php
                            $colors = $conn->query("SELECT color_hex, color_name FROM product_colors WHERE p_id={$p['p_id']} LIMIT 6");
                            if ($colors->rowCount() > 0) {
                                echo '<div style="display:flex; gap:5px; flex-wrap:wrap; justify-content:center;">';
                                while ($c = $colors->fetch()) {
                                    echo '<div style="width:22px;height:22px;background:'.htmlspecialchars($c['color_hex']).';border-radius:50%;border:1px solid #ddd;" title="'.htmlspecialchars($c['color_name']).'"></div>';
                                }
                                echo '</div>';
                            } else {
                                echo '<span style="color:#bbb;font-size:12px;">無色號</span>';
                            }
                            ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>詳細</strong></td>
                        <?php foreach ($group as $p): ?>
                            <td><a href="product.php?id=<?php echo $p['p_id']; ?>" class="btn btn-primary" style="font-size:12px; padding:6px 12px;">查看詳細</a></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="compare-actions">
            <a href="products.php" class="btn btn-outline" style="padding:12px 20px;">← 返回產品</a>
            <a href="compare.php?clear=1" class="btn btn-outline" style="padding:12px 20px; flex:1; text-align:center;">清除全部比較</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

</body>
</html>