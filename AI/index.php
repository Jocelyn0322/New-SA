<?php
// session 必須在所有 HTML 輸出之前啟動
if (session_status() === PHP_SESSION_NONE) session_start();

// 告訴 産品/header.php 路徑基底
$base   = '../產品/';
$aiBase = '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Skin Lab</title>
    <link rel="stylesheet" href="../產品/style.css?v=2">
    <style>
    /* ── Page ─────────────────────────────────────────────── */
    body {
        background: linear-gradient(155deg, #f5f0ea 0%, #ede8f2 100%);
        min-height: 100vh;
    }

    /* ── Hero ─────────────────────────────────────────────── */
    .ail-hero {
        position: relative;
        overflow: hidden;
        padding: 80px 24px 64px;
        background: linear-gradient(140deg, #faf5ee 0%, #f5f0f8 55%, #eef4f8 100%);
        border-bottom: 1px solid #e8e0d8;
        text-align: center;
    }
    .ail-hero::before {
        content: '';
        position: absolute;
        width: 480px; height: 480px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(196,168,192,.45), transparent 70%);
        top: -180px; right: -100px;
        pointer-events: none;
    }
    .ail-hero::after {
        content: '';
        position: absolute;
        width: 360px; height: 360px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(168,181,162,.4), transparent 70%);
        bottom: -100px; left: -60px;
        pointer-events: none;
    }
    .ail-hero__inner {
        position: relative;
        z-index: 1;
        max-width: 660px;
        margin: 0 auto;
    }
    .ail-badge {
        display: inline-block;
        padding: 5px 16px;
        border-radius: 999px;
        background: #b5a8c0;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 22px;
    }
    .ail-hero__title {
        font-size: clamp(2rem, 5vw, 3.2rem);
        font-weight: 800;
        line-height: 1.18;
        color: #47403a;
        margin: 0 0 20px;
        letter-spacing: -.025em;
    }
    .ail-hero__sub {
        font-size: 1.04rem;
        line-height: 1.85;
        color: #787068;
        max-width: 500px;
        margin: 0 auto 36px;
    }
    .ail-hero__actions {
        display: flex;
        gap: 14px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 44px;
    }
    .ail-btn-primary {
        display: inline-block;
        padding: 13px 32px;
        border-radius: 999px;
        background: #b5a8c0;
        color: #fff;
        font-weight: 700;
        font-size: 15px;
        text-decoration: none;
        box-shadow: 0 8px 22px rgba(181,168,192,.38);
        transition: transform .2s, box-shadow .2s;
    }
    .ail-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(181,168,192,.50);
        background: #c4b8d0;
    }
    .ail-btn-secondary {
        display: inline-block;
        padding: 12px 28px;
        border-radius: 999px;
        background: transparent;
        border: 1.5px solid #c4a882;
        color: #8a6840;
        font-weight: 600;
        font-size: 15px;
        text-decoration: none;
        transition: background .2s;
    }
    .ail-btn-secondary:hover { background: #faf5ed; }

    /* Colour swatches */
    .ail-swatches {
        display: flex;
        gap: 12px;
        justify-content: center;
        align-items: center;
    }
    .ail-swatches span {
        width: 46px; height: 46px;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 4px 12px rgba(0,0,0,.14);
        transition: transform .25s;
    }
    .ail-swatches span:hover { transform: scale(1.18) translateY(-3px); }

    /* ── Shared helpers ────────────────────────────────────── */
    .ail-section-inner {
        max-width: 1080px;
        margin: 0 auto;
        padding: 0 24px;
    }
    .ail-section-title {
        font-size: 1.45rem;
        font-weight: 700;
        color: #47403a;
        text-align: center;
        margin: 0 0 36px;
        letter-spacing: -.01em;
    }

    /* ── Steps ─────────────────────────────────────────────── */
    .ail-steps { padding: 72px 0; }
    .ail-steps__grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
    }
    .ail-step {
        background: #fdfaf6;
        border: 1px solid #e8e0d8;
        border-radius: 22px;
        padding: 36px 26px 30px;
        text-align: center;
        box-shadow: 0 4px 18px rgba(100,80,60,.07);
        transition: transform .22s, box-shadow .22s;
        position: relative;
    }
    .ail-step:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(100,80,60,.13);
    }
    .ail-step--mid::before,
    .ail-step--mid::after {
        content: '';
        position: absolute;
        top: 50%; width: 22px; height: 1px;
        background: #d8cec8;
    }
    .ail-step--mid::before { left: -22px; }
    .ail-step--mid::after  { right: -22px; }
    .ail-step__num {
        font-size: 10px; font-weight: 800;
        letter-spacing: .14em; color: #c4a882;
        text-transform: uppercase; margin-bottom: 14px;
    }
    .ail-step__icon { font-size: 2.4rem; line-height: 1; margin-bottom: 14px; }
    .ail-step h3 { font-size: 1.05rem; font-weight: 700; color: #47403a; margin: 0 0 10px; }
    .ail-step p  { font-size: .875rem; color: #787068; line-height: 1.72; margin: 0; }

    /* ── Feature Cards ─────────────────────────────────────── */
    .ail-features { padding: 0 0 80px; }
    .ail-features__grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }
    .ail-card {
        display: flex; flex-direction: column;
        border-radius: 22px;
        padding: 30px 24px 24px;
        text-decoration: none;
        border: 1px solid transparent;
        transition: transform .22s, box-shadow .22s;
        position: relative;
    }
    .ail-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 32px rgba(100,80,60,.15);
    }
    .ail-card--rose     { background: #f9f2f2; border-color: #ead5d8; }
    .ail-card--sage     { background: #f2f7f0; border-color: #cfe2cb; }
    .ail-card--blue     { background: #f0f4f8; border-color: #ccd9e5; }
    .ail-card--lavender { background: #f4f0f8; border-color: #d8d0e8; }
    .ail-card__icon { font-size: 2rem; line-height: 1; margin-bottom: 16px; display: block; }
    .ail-card h3 { font-size: 1rem; font-weight: 700; color: #47403a; margin: 0 0 8px; }
    .ail-card p  { font-size: .875rem; color: #787068; line-height: 1.68; margin: 0; flex: 1; }
    .ail-card__arrow {
        display: inline-block; margin-top: 16px;
        font-size: 1.1rem; color: #b5a8c0; font-weight: 700;
        transition: transform .2s;
    }
    .ail-card:hover .ail-card__arrow { transform: translateX(4px); }

    /* ── RWD ──────────────────────────────────────────────── */
    @media (max-width: 900px) {
        .ail-features__grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 680px) {
        .ail-steps__grid { grid-template-columns: 1fr; gap: 16px; }
        .ail-step--mid::before,
        .ail-step--mid::after { display: none; }
        .ail-hero { padding: 60px 20px 48px; }
        .ail-hero__title { font-size: 2rem; }
    }
    @media (max-width: 480px) {
        .ail-features__grid { grid-template-columns: 1fr; }
        .ail-hero__sub { font-size: .95rem; }
    }
    </style>
</head>
<body>
    <?php include '../產品/header.php'; ?>

    <!-- ── Hero ── -->
    <section class="ail-hero">
        <div class="ail-hero__inner">
            <span class="ail-badge">AI Skin Lab</span>
            <h1 class="ail-hero__title">用 AI 找到<br>最適合你的底妝</h1>
            <p class="ail-hero__sub">
                透過簡單問答與相機拍照，讓 AI 幫你分析膚色、膚質，<br>
                精準推薦最匹配的底妝產品與色號。
            </p>
            <div class="ail-hero__actions">
                <a href="story1.php" class="ail-btn-primary">立即開始測試</a>
                <a href="../產品/index.php" class="ail-btn-secondary">瀏覽產品</a>
            </div>
            <div class="ail-swatches">
                <span style="background:#c9b89e"></span>
                <span style="background:#c4a5a5"></span>
                <span style="background:#b5a8c0"></span>
                <span style="background:#a8b5a2"></span>
                <span style="background:#9db3c5"></span>
            </div>
        </div>
    </section>

    <!-- ── 三步驟 ── -->
    <section class="ail-steps">
        <div class="ail-section-inner">
            <h2 class="ail-section-title">三步驟完成分析</h2>
            <div class="ail-steps__grid">
                <div class="ail-step">
                    <div class="ail-step__num">01</div>
                    <div class="ail-step__icon">📝</div>
                    <h3>問答評估</h3>
                    <p>回答膚色與膚質問卷，系統預先判定你的基本膚況與冷暖調。</p>
                </div>
                <div class="ail-step ail-step--mid">
                    <div class="ail-step__num">02</div>
                    <div class="ail-step__icon">📸</div>
                    <h3>相機分析</h3>
                    <p>開啟相機拍攝臉部，AI 即時讀取膚色座標與活體特徵。</p>
                </div>
                <div class="ail-step">
                    <div class="ail-step__num">03</div>
                    <div class="ail-step__icon">💄</div>
                    <h3>精準推薦</h3>
                    <p>綜合問答與影像資料，推薦最匹配的底妝色號與護膚成分。</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── 功能卡片 ── -->
    <section class="ail-features">
        <div class="ail-section-inner">
            <h2 class="ail-section-title">更多功能</h2>
            <div class="ail-features__grid">
                <a href="story1.php" class="ail-card ail-card--rose">
                    <span class="ail-card__icon">🔬</span>
                    <h3>AI 膚質測試</h3>
                    <p>問答＋相機雙重分析，準確判定你的膚色與膚質類型。</p>
                    <span class="ail-card__arrow">→</span>
                </a>
                <a href="../產品/skin-match.php" class="ail-card ail-card--sage">
                    <span class="ail-card__icon">🎨</span>
                    <h3>膚色配對</h3>
                    <p>從膚色目錄快速挑選，找到適合色系的底妝與腮紅。</p>
                    <span class="ail-card__arrow">→</span>
                </a>
                <a href="../產品/products.php" class="ail-card ail-card--blue">
                    <span class="ail-card__icon">🛍️</span>
                    <h3>產品列表</h3>
                    <p>瀏覽所有美妝商品，搭配 AI 結果更精準挑選。</p>
                    <span class="ail-card__arrow">→</span>
                </a>
                <a href="../產品/favorite.php" class="ail-card ail-card--lavender">
                    <span class="ail-card__icon">⭐</span>
                    <h3>收藏 &amp; 比較</h3>
                    <p>把候選產品加入收藏，並排比較規格與色號。</p>
                    <span class="ail-card__arrow">→</span>
                </a>
            </div>
        </div>
    </section>

    <?php include '../產品/footer.php'; ?>
</body>
</html>
