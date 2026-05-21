<?php
session_start();
include 'db.php';

$selected_tone = $_GET['tone'] ?? '';

// 取所有膚色
$tones = $conn->query("SELECT * FROM skintones ORDER BY id");
$all_tones = [];
while($t = $tones->fetch()) $all_tones[] = $t;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COSMETIC — 膚色配對</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="page">

<div class="products">
    <div style="max-width: 1400px; margin: 0 auto; margin-bottom: 30px;">
        <h2 style="color: #333; font-size: 28px; font-weight: 600;">🎨 膚色配對推薦</h2>
        <p style="color: #999; margin-top: 8px;">選擇你的膚色，系統會推薦最適合的粉底色號</p>
    </div>

    <div style="max-width: 1000px; margin: 0 auto; margin-bottom: 40px;">
        <h3 style="font-size: 20px; margin-bottom: 20px;">請選擇你的膚色</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
        <?php foreach($all_tones as $tone){ ?>
            <a href="?tone=<?php echo $tone['id']; ?>"
               style="
                   padding: 15px;
                   background: white;
                   border-radius: 15px;
                   box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                   text-decoration: none;
                   color: #333;
                   text-align: center;
                   transition: transform 0.2s ease;
                   border: <?php echo $selected_tone == $tone['id'] ? '3px solid #efc6cd' : '1px solid #eee'; ?>;
               "
               onmouseover="this.style.transform='translateY(-3px)'"
               onmouseout="this.style.transform='translateY(0)'"
            >
                <div style="
                    width: 60px;
                    height: 60px;
                    background: <?php echo $tone['hexvalue']; ?>;
                    border-radius: 50%;
                    margin: 0 auto 10px;
                    border: 2px solid #ddd;
                "></div>
                <div style="font-size: 14px; font-weight: 600;"><?php echo $tone['tonename']; ?></div>
                <div style="font-size: 12px; color: #999; margin-top: 5px;"><?php echo $tone['tonecategory']; ?></div>
            </a>
        <?php } ?>
        </div>
    </div>

    <?php if($selected_tone){ ?>
        <?php
        // 取得選中的膚色資訊
        $tone = $conn->query("SELECT * FROM skintones WHERE id='$selected_tone'")->fetch();

        // 查詢所有粉底產品及其色號
        $products = $conn->query("
            SELECT p.*, STRING_AGG(pc.color_name, ',') as colors, STRING_AGG(pc.color_hex, ',') as color_hexes
            FROM data p
            LEFT JOIN product_colors pc ON p.id = pc.p_id
            WHERE p.category='底妝'
            GROUP BY p.id
        ");
        ?>

        <div style="max-width: 1400px; margin: 0 auto; margin-bottom: 30px;">
            <h3 style="font-size: 24px; margin-bottom: 10px;">
                💄 推薦適合
                <span style="
                    background: <?php echo $tone['hexvalue']; ?>;
                    padding: 8px 16px;
                    border-radius: 20px;
                    color: white;
                    display: inline-block;
                    font-weight: 600;
                "><?php echo $tone['tonename']; ?></span>
                的粉底
            </h3>
            <p style="color: #666;">系統根據你的膚色推薦最適合的粉底色號</p>
        </div>

        <div class="product-grid">
        <?php
        while($p = $products->fetch()){
            $isFav = in_array($p['p_id'], $_SESSION['favorite'] ?? []);
            $colors = explode(',', $p['colors']);
            $color_hexes = explode(',', $p['color_hexes']);
        ?>
            <div class="product-card">
                <div class="fav-btn">
                    <?php if($isFav){ ?>
                        <form action="remove_favorite.php" method="POST" style="margin:0; padding:0;">
                            <input type="hidden" name="id" value="<?php echo $p['p_id']; ?>">
                            <button type="submit" class="heart active">❤️</button>
                        </form>
                    <?php }else{ ?>
                        <form action="add_favorite.php" method="POST" style="margin:0; padding:0;">
                            <input type="hidden" name="id" value="<?php echo $p['p_id']; ?>">
                            <button type="submit" class="heart">🤍</button>
                        </form>
                    <?php } ?>
                </div>

                <img src="images/<?php echo $p['p_id']; ?>.jpg" alt="<?php echo htmlspecialchars($p['name']); ?>">

                <div class="product-card-inner">
                    <h3><?php echo htmlspecialchars($p['name']); ?></h3>
                    <p><?php echo htmlspecialchars($p['brand']); ?> - <?php echo htmlspecialchars($p['category']); ?></p>

                    <!-- 顯示色號預覽 -->
                    <?php if($p['colors'] && $p['colors'] != ''){ ?>
                        <div style="margin: 8px 0; display: flex; gap: 4px; flex-wrap: wrap;">
                            <?php
                            for($i = 0; $i < min(4, count($colors)); $i++){
                                if($colors[$i] && $color_hexes[$i]){
                                    echo '<div style="width:20px; height:20px; background:'.$color_hexes[$i].'; border-radius:50%; border:1px solid #ddd;" title="'.$colors[$i].'"></div>';
                                }
                            }
                            ?>
                        </div>
                    <?php } ?>

                    <div class="product-actions">
                        <a href="product.php?id=<?php echo $p['p_id']; ?>" class="btn btn-primary">查看詳細</a>
                        <form action="add_compare.php" method="POST" style="flex: 1;">
                            <input type="hidden" name="id" value="<?php echo $p['p_id']; ?>">
                            <button type="submit" class="btn btn-outline">比較</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php } ?>
        </div>
    <?php } ?>
</div>

    </main>

    <?php include 'footer.php'; ?>

</body>
</html>