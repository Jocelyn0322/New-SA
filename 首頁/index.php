<?php
session_start();
require 'db.php';

// 從資料庫抓輪播圖
$stmt = $pdo->query("
    SELECT id, image_path 
    FROM carousel_images
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
");
$slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 如果資料庫沒有圖片，才用預設圖
if (empty($slides)) {
    $slides = [
        ["id" => null, "image_path" => "images/slide1.jpg"],
        ["id" => null, "image_path" => "images/slide2.jpg"],
        ["id" => null, "image_path" => "images/slide3.jpg"]
    ];
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$menuCards = [
    [
        "title" => "產品資訊",
        "link" => "product.php"
    ],
    [
        "title" => "ＡＩ辨識臉部皮膚",
        "link" => "ai-skin.php"
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
                    <img src="<?php echo htmlspecialchars($slide['image_path']); ?>" alt="輪播圖片">
                    <?php if ($isAdmin && $slide['id']): ?>
                        <div class="slide-admin-btns">
                            <button class="slide-edit-btn" onclick="editSlide(<?php echo $slide['id']; ?>)">✏️ 編輯</button>
                            <button class="slide-delete-btn" onclick="deleteSlide(<?php echo $slide['id']; ?>)">🗑️ 刪除</button>
                        </div>
                    <?php endif; ?>
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

    function editSlide(id) {
        // 重定向到管理後台的編輯輪播圖頁面
        window.location.href = 'admin.php?action=edit_carousel&id=' + id;
    }

    function deleteSlide(id) {
        if (confirm('確定要刪除這張輪播圖嗎？')) {
            // 發送刪除請求
            fetch('api/delete_carousel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('刪除成功');
                    location.reload();
                } else {
                    alert('刪除失敗：' + (data.message || '未知錯誤'));
                }
            })
            .catch(error => {
                alert('刪除失敗：' + error);
            });
        }
    }
</script>
</body>
</html>