<?php
require_once __DIR__ . '/../auth_check.php';
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
        background: #f7f6f6;
        min-height: 100vh;
    }

    /* ── Hero ─────────────────────────────────────────────── */
    .ail-hero {
        position: relative;
        overflow: hidden;
        padding: 72px 24px 64px;
        background: linear-gradient(135deg, #3d1520 0%, #6b2d3e 100%);
    }
    .ail-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
    }
    .ail-hero__inner {
        position: relative;
        z-index: 1;
        max-width: 1080px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        align-items: center;
    }
    .ail-hero__text { text-align: left; }
    .ail-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #f9cfd8;
        margin-bottom: 20px;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.2);
        padding: 5px 14px;
        border-radius: 99px;
    }
    .ail-hero__title {
        font-size: clamp(1.8rem, 3.5vw, 2.8rem);
        font-weight: 700;
        line-height: 1.22;
        color: white;
        margin: 0 0 18px;
        letter-spacing: -.025em;
    }
    .ail-hero__title em { color: #f9cfd8; font-style: normal; }
    .ail-hero__sub {
        font-size: 1rem;
        line-height: 1.85;
        color: rgba(255,255,255,.7);
        margin: 0 0 32px;
    }
    .ail-hero__actions {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 36px;
    }
    .ail-hero__tags {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .ail-hero__tag {
        font-size: 12px;
        color: rgba(255,255,255,.6);
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.15);
        padding: 4px 12px;
        border-radius: 99px;
    }
    .ail-btn-primary {
        display: inline-block;
        padding: 13px 32px;
        border-radius: 999px;
        background: #c26b7c;
        color: #fff;
        font-weight: 700;
        font-size: 15px;
        text-decoration: none;
        box-shadow: 0 8px 22px rgba(194,107,124,.4);
        transition: transform .2s, box-shadow .2s, background .2s;
    }
    .ail-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(194,107,124,.55);
        background: #b05a6c;
    }
    .ail-btn-secondary {
        display: inline-block;
        padding: 12px 28px;
        border-radius: 999px;
        background: rgba(255,255,255,.12);
        border: 1.5px solid rgba(255,255,255,.35);
        color: white;
        font-weight: 600;
        font-size: 15px;
        text-decoration: none;
        transition: background .2s;
    }
    .ail-btn-secondary:hover { background: rgba(255,255,255,.22); }

    /* ── Hero visual (camera mockup) ──────────────────────── */
    .ail-hero__visual {
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .ail-phone {
        width: 260px;
        height: 360px;
        background: rgba(255,255,255,.06);
        border: 1.5px solid rgba(255,255,255,.2);
        border-radius: 36px;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-shadow: 0 24px 60px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.1);
        backdrop-filter: blur(8px);
    }
    .ail-phone__notch {
        position: absolute;
        top: 14px;
        width: 60px; height: 10px;
        background: rgba(255,255,255,.15);
        border-radius: 99px;
    }
    .ail-scan-ring {
        width: 140px; height: 140px;
        border-radius: 50%;
        border: 2px dashed rgba(249,207,216,.5);
        display: flex; align-items: center; justify-content: center;
        position: relative;
        animation: spin 8s linear infinite;
    }
    .ail-scan-ring::before {
        content: '';
        position: absolute;
        width: 120px; height: 120px;
        border-radius: 50%;
        background: rgba(194,107,124,.12);
        border: 1.5px solid rgba(194,107,124,.3);
    }
    .ail-scan-face {
        font-size: 52px;
        z-index: 1;
        animation: pulse 2.5s ease-in-out infinite;
    }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.06); } }
    .ail-scan-label {
        margin-top: 18px;
        font-size: 11px;
        color: rgba(255,255,255,.5);
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }
    .ail-scan-dots {
        display: flex; gap: 8px; margin-top: 16px;
    }
    .ail-scan-dots span {
        width: 20px; height: 20px;
        border-radius: 50%;
        border: 1.5px solid rgba(255,255,255,.2);
    }
    .ail-corner {
        position: absolute;
        width: 18px; height: 18px;
        border-color: #c26b7c;
        border-style: solid;
    }
    .ail-corner--tl { top: 18px; left: 18px; border-width: 2px 0 0 2px; border-radius: 4px 0 0 0; }
    .ail-corner--tr { top: 18px; right: 18px; border-width: 2px 2px 0 0; border-radius: 0 4px 0 0; }
    .ail-corner--bl { bottom: 18px; left: 18px; border-width: 0 0 2px 2px; border-radius: 0 0 0 4px; }
    .ail-corner--br { bottom: 18px; right: 18px; border-width: 0 2px 2px 0; border-radius: 0 0 4px 0; }

    /* ── Shared helpers ────────────────────────────────────── */
    .ail-section-inner {
        max-width: 1080px;
        margin: 0 auto;
        padding: 0 24px;
    }
    .ail-section-title {
        font-size: 1.45rem;
        font-weight: 700;
        color: #1a1a1a;
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
        background: #ffffff;
        border: 1px solid #e8e8e8;
        border-radius: 22px;
        padding: 36px 26px 30px;
        text-align: center;
        box-shadow: 0 4px 18px rgba(0,0,0,.06);
        transition: transform .22s, box-shadow .22s;
        position: relative;
    }
    .ail-step:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(194,107,124,.15);
        border-color: #f5c6d0;
    }
    .ail-step--mid::before,
    .ail-step--mid::after {
        content: '';
        position: absolute;
        top: 50%; width: 22px; height: 1px;
        background: #e8e8e8;
    }
    .ail-step--mid::before { left: -22px; }
    .ail-step--mid::after  { right: -22px; }
    .ail-step__num {
        font-size: 10px; font-weight: 800;
        letter-spacing: .14em; color: #c26b7c;
        text-transform: uppercase; margin-bottom: 14px;
    }
    .ail-step__icon { font-size: 2.4rem; line-height: 1; margin-bottom: 14px; }
    .ail-step h3 { font-size: 1.05rem; font-weight: 700; color: #1a1a1a; margin: 0 0 10px; }
    .ail-step p  { font-size: .875rem; color: #4b5563; line-height: 1.72; margin: 0; }

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
        border: 1px solid #e8e8e8;
        background: #ffffff;
        transition: transform .22s, box-shadow .22s, border-color .22s;
        position: relative;
    }
    .ail-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 32px rgba(194,107,124,.15);
        border-color: #f5c6d0;
    }
    .ail-card__icon { font-size: 2rem; line-height: 1; margin-bottom: 16px; display: block; }
    .ail-card h3 { font-size: 1rem; font-weight: 700; color: #1a1a1a; margin: 0 0 8px; }
    .ail-card p  { font-size: .875rem; color: #4b5563; line-height: 1.68; margin: 0; flex: 1; }
    .ail-card__arrow {
        display: inline-block; margin-top: 16px;
        font-size: 1.1rem; color: #c26b7c; font-weight: 700;
        transition: transform .2s;
    }
    .ail-card:hover .ail-card__arrow { transform: translateX(4px); }

    /* ── RWD ──────────────────────────────────────────────── */
    @media (max-width: 900px) {
        .ail-features__grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 780px) {
        .ail-hero__inner { grid-template-columns: 1fr; gap: 40px; }
        .ail-hero__text { text-align: center; }
        .ail-hero__actions { justify-content: center; }
        .ail-hero__tags { justify-content: center; }
        .ail-hero__visual { display: none; }
    }
    @media (max-width: 680px) {
        .ail-steps__grid { grid-template-columns: 1fr; gap: 16px; }
        .ail-step--mid::before,
        .ail-step--mid::after { display: none; }
        .ail-hero { padding: 60px 20px 48px; }
        .ail-hero__title { font-size: 1.8rem; }
    }
    @media (max-width: 480px) {
        .ail-features__grid { grid-template-columns: 1fr; }
        .ail-hero__sub { font-size: .95rem; }
    }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>

    <!-- ── Hero ── -->
    <section class="ail-hero">
        <canvas class="fw-canvas" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;"></canvas>
        <div class="ail-hero__inner">
            <!-- 左側文字 -->
            <div class="ail-hero__text">
                <div class="ail-badge">✨ AI 膚質分析系統</div>
                <h1 class="ail-hero__title">讓 AI 幫你找到<br><em>最適合的底妝色號</em></h1>
                <p class="ail-hero__sub">
                    透過問答與即時相機分析，精準讀取你的膚色座標與膚質類型，推薦最匹配的底妝產品。
                </p>
                <div class="ail-hero__actions">
                    <a href="story1.php" class="ail-btn-primary">立即開始分析</a>
                    <a href="../產品/products.php" class="ail-btn-secondary">瀏覽產品</a>
                </div>
                <div style="display:inline-flex;align-items:center;gap:7px;
                     background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);
                     border-radius:99px;padding:6px 14px;font-size:12px;color:rgba(255,255,255,.85);
                     margin-bottom:14px;">
                  🔒 此系統不會儲存您臉部的照片或影片，請放心使用
                </div>
                <div class="ail-hero__tags">
                    <span class="ail-hero__tag">膚色偵測</span>
                    <span class="ail-hero__tag">活體驗證</span>
                    <span class="ail-hero__tag">AI 推薦</span>
                    <span class="ail-hero__tag">成分建議</span>
                </div>
            </div>
            <!-- 右側視覺 -->
            <div class="ail-hero__visual">
                <div class="ail-phone">
                    <div class="ail-phone__notch"></div>
                    <div class="ail-corner ail-corner--tl"></div>
                    <div class="ail-corner ail-corner--tr"></div>
                    <div class="ail-corner ail-corner--bl"></div>
                    <div class="ail-corner ail-corner--br"></div>
                    <div class="ail-scan-ring">
                        <div class="ail-scan-face">🫦</div>
                    </div>
                    <div class="ail-scan-label">Analyzing...</div>
                    <div class="ail-scan-dots">
                        <span style="background:#c9b89e;"></span>
                        <span style="background:#c4a5a5;"></span>
                        <span style="background:#e8c4a0;"></span>
                        <span style="background:#d4a89a;"></span>
                    </div>
                </div>
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


    <?php include '../產品/footer.php'; ?>
<script src="<?= BASE_URL ?>/fireworks.js"></script>
</body>
</html>
