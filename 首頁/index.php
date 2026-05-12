<?php
session_start();
require 'db.php';

$carouselMsg = $_SESSION['carousel_msg'] ?? null;
unset($_SESSION['carousel_msg']);

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
    <style>
        /* 輪播管理 FAB */
        .carousel-edit-fab {
            position: absolute;
            bottom: 14px;
            right: 14px;
            z-index: 50;
            background: rgba(0,0,0,0.55);
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 7px 14px;
            font-size: 13px;
            cursor: pointer;
            backdrop-filter: blur(4px);
            transition: background 0.2s;
        }
        .carousel-edit-fab:hover { background: rgba(0,0,0,0.8); }

        /* 管理 Modal */
        .cmgr-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 2000;
            justify-content: center;
            align-items: flex-start;
            padding-top: 60px;
        }
        .cmgr-overlay.open { display: flex; }

        .cmgr-panel {
            background: #fff;
            border-radius: 14px;
            width: 90%;
            max-width: 640px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 28px;
            position: relative;
        }
        .cmgr-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .cmgr-header h3 { font-size: 18px; margin: 0; }
        .cmgr-close {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #888;
        }
        .cmgr-upload {
            display: flex;
            gap: 10px;
            align-items: center;
            background: #f8f8f8;
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 22px;
        }
        .cmgr-upload input[type="file"] { flex: 1; font-size: 13px; }
        .cmgr-upload button {
            padding: 8px 18px;
            background: #efc6cd;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
        }
        .cmgr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
        }
        .cmgr-card {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eee;
            position: relative;
        }
        .cmgr-card img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            display: block;
        }
        .cmgr-card-del {
            width: 100%;
            padding: 6px;
            background: #dc3545;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .cmgr-msg {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .cmgr-msg.success { background: #d4edda; color: #155724; }
        .cmgr-msg.error   { background: #f8d7da; color: #721c24; }
    </style>
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

            <?php if ($isAdmin): ?>
                <button class="carousel-edit-fab" onclick="document.getElementById('carouselMgr').classList.add('open')">✏️ 管理輪播</button>
            <?php endif; ?>

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

<?php if ($isAdmin): ?>
<div class="cmgr-overlay" id="carouselMgr">
    <div class="cmgr-panel">
        <div class="cmgr-header">
            <h3>🖼️ 輪播照片管理</h3>
            <button class="cmgr-close" onclick="document.getElementById('carouselMgr').classList.remove('open')">✕</button>
        </div>

        <?php if ($carouselMsg): ?>
            <div class="cmgr-msg <?php echo htmlspecialchars($carouselMsg['type']); ?>">
                <?php echo htmlspecialchars($carouselMsg['text']); ?>
            </div>
        <?php endif; ?>

        <form class="cmgr-upload" method="post" enctype="multipart/form-data" action="carousel_manage.php">
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" required>
            <button type="submit">上傳新圖</button>
        </form>

        <?php
        $allSlides = $pdo->query("SELECT id, image_path FROM carousel_images WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
        ?>
        <?php if (empty($allSlides)): ?>
            <p style="color:#999;text-align:center;">目前沒有輪播圖片</p>
        <?php else: ?>
            <div class="cmgr-grid">
                <?php foreach ($allSlides as $s): ?>
                    <div class="cmgr-card">
                        <img src="<?php echo htmlspecialchars($s['image_path']); ?>" alt="">
                        <form method="post" action="carousel_manage.php" onsubmit="return confirm('確定刪除？')">
                            <input type="hidden" name="delete_id" value="<?php echo (int)$s['id']; ?>">
                            <button type="submit" class="cmgr-card-del">🗑️ 刪除</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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