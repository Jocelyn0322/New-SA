<?php
session_start();
include 'db.php';
include 'header.php';

$selected_tone = $_GET['tone'] ?? '';

// 取所有膚色
$tones = $conn->query("SELECT * FROM skintones");
$all_tones = [];
while($t = $tones->fetch_assoc()) $all_tones[] = $t;
?>

<div class="products">
    <h2>🎨 選擇你的膚色</h2>
    
    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 30px; max-width: 900px;">
    <?php foreach($all_tones as $tone){ ?>
        <a href="?tone=<?php echo $tone['id']; ?>" 
           style="
               width: 100%; 
               height: 60px;
               background: <?php echo $tone['HexValue']; ?>;
               border-radius: 10px;
               border: <?php echo $selected_tone == $tone['id'] ? '3px solid #efc6cd' : '2px solid #ddd'; ?>;
               display: flex;
               align-items: center;
               justify-content: center;
               text-decoration: none;
               color: #333;
               font-weight: 600;
               font-size: 12px;
           "
           title="<?php echo $tone['ToneName']; ?>"
        >
            <?php echo $tone['ToneName']; ?>
        </a>
    <?php } ?>
    </div>

    <?php if($selected_tone){ ?>
        <?php
        // 找出適合這個膚色的粉底色號
        $tone = $conn->query("SELECT * FROM skintones WHERE id='$selected_tone'")->fetch_assoc();
        
        // 查出所有粉底色號，計算匹配度
        $products = $conn->query("SELECT p.*, GROUP_CONCAT(pc.color_name) as colors FROM products p LEFT JOIN product_colors pc ON p.p_id = pc.p_id WHERE p.category='底妝' GROUP BY p.p_id");
        ?>
        
        <h3>💄 推薦適合 <span style="background: <?php echo $tone['HexValue']; ?>; padding: 5px 12px; border-radius: 15px; color: white; display: inline-block;"><?php echo $tone['ToneName']; ?></span> 的粉底</h3>
        
        <div class="product-grid">
        <?php 
        while($p = $products->fetch_assoc()){
            $isFav = in_array($p['p_id'], $_SESSION['favorite'] ?? []);
        ?>
            <div class="product-card">
                <div class="fav-btn">
                    <?php if($isFav){ ?>
                        <form action="remove_favorite.php" method="POST" style="margin:0;">
                            <input type="hidden" name="id" value="<?php echo $p['p_id']; ?>">
                            <button type="submit" class="heart active">❤️</button>
                        </form>
                    <?php }else{ ?>
                        <form action="add_favorite.php" method="POST" style="margin:0;">
                            <input type="hidden" name="id" value="<?php echo $p['p_id']; ?>">
                            <button type="submit" class="heart">🤍</button>
                        </form>
                    <?php } ?>
                </div>
                <img src="images/<?php echo $p['p_id']; ?>.jpg" alt="<?php echo $p['name']; ?>">
                <div class="product-card-inner">
                    <h3><?php echo $p['name']; ?></h3>
                    <p><?php echo $p['brand']; ?></p>
                    <div class="product-actions">
                        <a href="product.php?id=<?php echo $p['p_id']; ?>" class="btn btn-primary">查看</a>
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

<?php include 'footer.php'; ?>