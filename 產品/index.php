<?php
session_start();
include 'db.php';

$slides = [
    "images/slide1.jpg",
    "images/slide2.jpg",
    "images/slide3.jpg"
];

// 取最新的6個產品作為推薦
$sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT 6";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>首頁 - Makeup</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="page">
        <!-- 輪播區 -->
        <section class="hero">
            <div class="slider">
                <?php foreach ($slides as $index => $slide): ?>
                    <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($slide); ?>" alt="輪播圖片">
                    </div>
                <?php endforeach; ?>

                <div class="hero-text">💄 精選美妝</div>

                <button class="slider-btn prev" onclick="changeSlide(-1)">❮</button>
                <button class="slider-btn next" onclick="changeSlide(1)">❯</button>

                <div class="dots">
                    <?php foreach ($slides as $index => $slide): ?>
                        <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $index; ?>)"></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 推薦商品區 -->
        <section class="features">
            <div style="max-width: 1400px; margin: 0 auto; padding: 0 40px;">
                <h2 style="font-size: 28px; font-weight: 600; margin-bottom: 10px; color: #333;">✨ 熱門推薦</h2>
                <p style="color: #999; margin-bottom: 30px;">最新上架的精選商品</p>

                <div class="product-grid">
                <?php 
                $favorites = $_SESSION['favorite'] ?? [];
                while($row = $result->fetch_assoc()){
                    $isFav = in_array($row['p_id'], $favorites);
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
                            <p><?php echo htmlspecialchars($row['brand']); ?></p>

                            <div class="product-actions">
                                <a href="product.php?id=<?php echo $row['p_id']; ?>" class="btn btn-primary">查看</a>
                                <form action="add_compare.php" method="POST" style="flex: 1;">
                                    <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                                    <button type="submit" class="btn btn-outline">比較</button>
                                </form>
                            </div>
                        </div>

                    </div>

                <?php } ?>
                </div>

                <div style="text-align: center; margin-top: 40px;">
                    <a href="products.php" class="btn btn-primary" style="padding: 12px 32px; font-size: 15px; display: inline-block;">查看全部產品</a>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.dot');
        let currentSlide = 0;
        let autoSlide;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });

            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === index);
            });

            currentSlide = index;
        }

        function changeSlide(step) {
            let newIndex = currentSlide + step;

            if (newIndex < 0) {
                newIndex = slides.length - 1;
            } else if (newIndex >= slides.length) {
                newIndex = 0;
            }

            showSlide(newIndex);
            resetAutoSlide();
        }

        function goToSlide(index) {
            showSlide(index);
            resetAutoSlide();
        }

        function startAutoSlide() {
            autoSlide = setInterval(() => {
                let newIndex = currentSlide + 1;
                if (newIndex >= slides.length) {
                    newIndex = 0;
                }
                showSlide(newIndex);
            }, 3000);
        }

        function resetAutoSlide() {
            clearInterval(autoSlide);
            startAutoSlide();
        }

        startAutoSlide();
    </script>
</body>
</html>
        }

        function goToSlide(index) {
            showSlide(index);
            resetAutoSlide();
        }

        function startAutoSlide() {
            autoSlide = setInterval(() => {
                let newIndex = currentSlide + 1;
                if (newIndex >= slides.length) {
                    newIndex = 0;
                }
                showSlide(newIndex);
            }, 3000);
        }

        function resetAutoSlide() {
            clearInterval(autoSlide);
            startAutoSlide();
        }

        startAutoSlide();
    </script>
</body>
</html>
