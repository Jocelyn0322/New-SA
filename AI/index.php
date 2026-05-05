<?php
session_start();
require 'db.php';

// 從資料庫抓輪播圖
$stmt = $pdo->query("
    SELECT image_path 
    FROM carousel_images
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
");
$slides = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 如果資料庫沒有圖片，才用預設圖
if (empty($slides)) {
    $slides = [
        "images/slide1.jpg",
        "images/slide2.jpg",
        "images/slide3.jpg"
    ];
}

$menuCards = [
    [
        "title" => "產品資訊",
        "link" => "product.php"
    ],
    [
        "title" => "ＡＩ辨識臉部皮膚",
        "link" => "story1.php"
    ],
    [
        "title" => "影片交流",
        "link" => "video.php"
    ]
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>首頁</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'header.php'; ?>

<main class="page">
    <section class="hero">
        <div class="slider">
            <?php foreach ($slides as $index => $slide): ?>
                <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($slide); ?>" alt="輪播圖片">
                </div>
            <?php endforeach; ?>

            <?php if (count($slides) > 1): ?>
                <button class="slider-btn prev" onclick="changeSlide(-1)">❮</button>
                <button class="slider-btn next" onclick="changeSlide(1)">❯</button>

                <div class="dots">
                    <?php foreach ($slides as $index => $slide): ?>
                        <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>"
                              onclick="goToSlide(<?php echo $index; ?>)"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="features">
        <div class="card-list">
            <?php foreach ($menuCards as $card): ?>
                <a class="feature-card" href="<?php echo htmlspecialchars($card['link']); ?>">
                    <div class="feature-title"><?php echo htmlspecialchars($card['title']); ?></div>
                </a>
            <?php endforeach; ?>
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
        if (slides.length <= 1) return;

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