<?php
session_start();
include 'db.php';

$profile    = null;
$products   = [];
$hasProfile = false;
$toneHex    = '';

// ── Load user profile ─────────────────────────────────────────────
if (isset($_SESSION['user'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE username = ?");
        $stmt->execute([$_SESSION['user']]);
        $profile    = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $hasProfile = $profile && (!empty($profile['skin_type']) || !empty($profile['skin_tone']));
    } catch (Exception $e) {}
}

// ── Fetch & rank products ─────────────────────────────────────────
if ($hasProfile) {
    $skinType     = $profile['skin_type']     ?? '';
    $skinTone     = $profile['skin_tone']     ?? '';
    $skinConcerns = $profile['skin_concerns'] ?? '';
    $makeupFinish = $profile['makeup_finish'] ?? '';
    $makeupStyle  = $profile['makeup_style']  ?? '';
    $isSensitive  = mb_strpos($skinConcerns, '敏感肌', 0, 'UTF-8') !== false;

    // Try to get skin tone hex from skintones table (graceful fallback)
    try {
        $ts = $pdo->prepare("SELECT hexvalue FROM skintones WHERE tonename = ? LIMIT 1");
        $ts->execute([$skinTone]);
        $toneHex = $ts->fetchColumn() ?: '';
    } catch (Exception $e) { $toneHex = ''; }

    $skinTypeKw = [
        '乾性皮' => ['保濕','潤澤','水光','裸光','養膚','滋潤','精華','乳霜'],
        '混乾皮' => ['保濕','潤澤','服貼','裸光','輕薄','精華'],
        '油性皮' => ['控油','持妝','霧面','柔霧','無油','抗汗','長效'],
        '混油皮' => ['控油','持妝','霧面','柔霧','平衡','長效','輕薄'],
        '中性皮' => ['自然','平衡','輕薄','通用','裸妝','日常'],
        '敏感肌' => ['敏感','溫和','舒敏','無香料','無酒精','低刺激','修護'],
    ];
    $finishKw = [
        '霧面'    => ['霧面','控油','柔霧','無油','持妝'],
        '水光'    => ['水光','保濕','潤澤','光澤','水感'],
        '自然光感' => ['自然','裸妝','輕薄','日常'],
        '緞面'    => ['緞面','光感','光澤','亮澤'],
    ];
    $styleKw = [
        '日常通勤' => ['輕薄','自然','日常','裸妝'],
        '韓系裸妝' => ['保濕','裸妝','輕薄','水光'],
        '歐美濃妝' => ['持妝','遮瑕','高遮瑕','長效'],
        '派對夜妝' => ['光澤','持妝','緞面','長效'],
    ];
    $sensitiveKw = ['無香料','無酒精','溫和','舒敏','低刺激','敏感肌','保濕','養膚','修護'];

    try {
        $stmt = $pdo->query(
            "SELECT d.*, STRING_AGG(pc.color_name, ',' ORDER BY pc.color_id) AS color_names,
                    STRING_AGG(pc.color_hex,  ',' ORDER BY pc.color_id) AS color_hexes
             FROM data d
             LEFT JOIN product_colors pc ON d.id = pc.p_id
             WHERE d.category = '底妝'
             GROUP BY d.id
             ORDER BY d.created_at DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }

    $skw   = $skinTypeKw[$skinType] ?? $skinTypeKw['中性皮'];
    $fkw   = $finishKw[$makeupFinish] ?? [];
    $stykw = $styleKw[$makeupStyle]   ?? [];

    $ranked = [];
    foreach ($rows as $row) {
        $text = mb_strtolower(implode(' ', [
            $row['brand']        ?? '',
            $row['name']         ?? '',
            $row['purpose']      ?? '',
            $row['ingredients']  ?? '',
            $row['precautions']  ?? '',
        ]), 'UTF-8');

        $score   = 5.0;
        $reasons = [];

        foreach ($skw as $kw) {
            if (mb_strpos($text, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
                $score += 2.0;
                $reasons[] = $kw;
            }
        }
        foreach ($fkw as $kw) {
            if (mb_strpos($text, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) $score += 1.5;
        }
        foreach ($stykw as $kw) {
            if (mb_strpos($text, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) $score += 1.0;
        }
        if ($isSensitive) {
            foreach ($sensitiveKw as $kw) {
                if (mb_strpos($text, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
                    $score += 1.5;
                    break;
                }
            }
        }
        if (mb_strpos($text, '粉底', 0, 'UTF-8') !== false || mb_strpos($text, '底妝', 0, 'UTF-8') !== false) {
            $score += 1.0;
        }

        $colorNames = array_values(array_filter(array_map('trim', explode(',', $row['color_names'] ?? ''))));
        $colorHexes = array_values(array_filter(array_map('trim', explode(',', $row['color_hexes'] ?? ''))));

        $ranked[] = [
            'id'         => $row['id'],
            'brand'      => $row['brand'] ?? '',
            'name'       => $row['name']  ?? '',
            'purpose'    => $row['purpose'] ?? '',
            'score'      => round($score, 1),
            'reasons'    => array_values(array_unique($reasons)),
            'colorNames' => $colorNames,
            'colorHexes' => $colorHexes,
        ];
    }

    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
    $products = array_slice($ranked, 0, 8);
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>AI 專屬推薦 - Makeup</title>
    <style>
        .ai-profile-card {
            background: linear-gradient(135deg, #f9f0f2 0%, #ede8f0 60%, #edf0f5 100%);
            border: 1px solid #e3d8e8;
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 32px;
        }
        .ai-profile-card h2 {
            font-size: 20px;
            font-weight: 700;
            color: #5c4a5a;
            margin-bottom: 18px;
        }
        .profile-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .profile-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            border: 1.5px solid #dbd0e0;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 13px;
            color: #5c4a5a;
            font-weight: 600;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .profile-tag .tag-label {
            color: #a897b0;
            font-weight: 400;
            font-size: 11px;
        }
        .tone-dot {
            width: 13px;
            height: 13px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.12);
            display: inline-block;
            flex-shrink: 0;
        }
        .match-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #b5a8c0, #c4a5a5);
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 10px;
            z-index: 1;
            letter-spacing: 0.02em;
        }
        .match-reasons {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin: 6px 0 4px;
        }
        .match-reason-tag {
            background: #f3eef6;
            color: #8a6c99;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: 500;
        }
        .color-swatches {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin: 4px 0 6px;
        }
        .color-swatch {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.13);
        }
        .ai-cta-btn {
            display: inline-block;
            background: linear-gradient(135deg, #c4a5a5, #b5a8c0);
            color: white;
            padding: 10px 22px;
            border-radius: 24px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .ai-cta-btn:hover { opacity: 0.85; }
        .ai-cta-btn-large {
            padding: 13px 30px;
            font-size: 15px;
        }
        .ai-empty-state {
            text-align: center;
            padding: 70px 20px;
        }
        .ai-empty-state .icon {
            font-size: 52px;
            margin-bottom: 18px;
        }
        .ai-empty-state h3 {
            font-size: 22px;
            color: #6b5e78;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .ai-empty-state p {
            color: #aaa;
            font-size: 14px;
            margin-bottom: 28px;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }
        .section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        .section-sub {
            font-size: 13px;
            color: #bbb;
            margin-top: 3px;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="page">
<div class="products">

<?php if (!isset($_SESSION['user'])): ?>
    <!-- Not logged in -->
    <div class="ai-empty-state">
        <div class="icon">✨</div>
        <h3>登入後查看 AI 專屬推薦</h3>
        <p>完成 AI 膚質分析後，系統將根據您的膚質、膚色和妝感偏好，精選最適合您的底妝產品。</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="/SA/New-SA/首頁/login.php" class="ai-cta-btn ai-cta-btn-large">前往登入</a>
            <a href="/SA/New-SA/AI/index.php" class="ai-cta-btn ai-cta-btn-large"
               style="background:linear-gradient(135deg,#a8b5a2,#9db3c5);">去做 AI 分析</a>
        </div>
    </div>

<?php elseif (!$hasProfile): ?>
    <!-- Logged in but no AI profile -->
    <div class="ai-empty-state">
        <div class="icon">🔍</div>
        <h3>尚未完成 AI 膚質分析</h3>
        <p>完成分析後，系統會依照您的膚質、膚色與妝感偏好，為您精選最合適的底妝產品。</p>
        <a href="/SA/New-SA/AI/index.php" class="ai-cta-btn ai-cta-btn-large">開始 AI 膚質分析</a>
    </div>

<?php else: ?>
    <!-- AI Profile Card -->
    <div class="ai-profile-card">
        <h2>✨ 您的 AI 膚質分析檔案</h2>
        <div class="profile-tags">
            <?php if ($skinType): ?>
            <div class="profile-tag">
                <span class="tag-label">膚質</span>
                <?= htmlspecialchars($skinType) ?>
            </div>
            <?php endif; ?>

            <?php if ($skinTone): ?>
            <div class="profile-tag">
                <span class="tag-label">膚色</span>
                <?php if ($toneHex): ?>
                <span class="tone-dot" style="background:<?= htmlspecialchars($toneHex) ?>;"></span>
                <?php endif; ?>
                <?= htmlspecialchars($skinTone) ?>
            </div>
            <?php endif; ?>

            <?php if ($skinConcerns): ?>
                <?php foreach (array_filter(array_map('trim', explode(',', $skinConcerns))) as $concern): ?>
                <div class="profile-tag">
                    <span class="tag-label">膚況</span>
                    <?= htmlspecialchars($concern) ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($makeupFinish): ?>
            <div class="profile-tag">
                <span class="tag-label">妝感</span>
                <?= htmlspecialchars($makeupFinish) ?>
            </div>
            <?php endif; ?>

            <?php if ($makeupStyle): ?>
            <div class="profile-tag">
                <span class="tag-label">妝容風格</span>
                <?= htmlspecialchars($makeupStyle) ?>
            </div>
            <?php endif; ?>
        </div>

        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <a href="/SA/New-SA/AI/index.php" class="ai-cta-btn">重新 AI 檢測</a>
            <a href="/SA/New-SA/首頁/profile.php"
               style="font-size:13px;color:#a897b0;text-decoration:none;">編輯個人資料 →</a>
        </div>
    </div>

    <!-- Products -->
    <div class="section-head">
        <div>
            <div class="section-title">💄 為您推薦的底妝</div>
            <div class="section-sub">
                依膚質<?= $makeupFinish ? '、'. htmlspecialchars($makeupFinish) .'妝感' : '' ?>
                <?= $makeupStyle ? '、'. htmlspecialchars($makeupStyle) : '' ?>
                精選 · 共 <?= count($products) ?> 款
            </div>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div style="text-align:center;padding:50px;color:#bbb;">
            <p>目前沒有找到符合條件的產品，請稍後再試。</p>
        </div>
    <?php else: ?>
    <div class="product-grid">
    <?php
    $favorites = $_SESSION['favorite'] ?? [];
    foreach ($products as $p):
        $isFav = in_array($p['id'], $favorites);
    ?>
        <div class="product-card">
            <div class="match-badge">★ <?= $p['score'] ?></div>

            <div class="fav-btn">
                <?php if ($isFav): ?>
                    <form action="remove_favorite.php" method="POST" style="margin:0;">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="heart active">❤️</button>
                    </form>
                <?php else: ?>
                    <form action="add_favorite.php" method="POST" style="margin:0;">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="heart">🤍</button>
                    </form>
                <?php endif; ?>
            </div>

            <img src="images/<?= $p['id'] ?>.jpg"
                 alt="<?= htmlspecialchars($p['name']) ?>"
                 onerror="this.style.background='#f5eff7';this.style.minHeight='160px';this.src='';this.onerror=null;">

            <div class="product-card-inner">
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <p><?= htmlspecialchars($p['brand']) ?></p>

                <?php if (!empty($p['reasons'])): ?>
                <div class="match-reasons">
                    <?php foreach (array_slice($p['reasons'], 0, 3) as $r): ?>
                    <span class="match-reason-tag"><?= htmlspecialchars($r) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($p['colorHexes'])): ?>
                <div class="color-swatches">
                    <?php foreach (array_slice($p['colorHexes'], 0, 6) as $i => $hex): ?>
                    <span class="color-swatch"
                          style="background:<?= htmlspecialchars($hex) ?>;"
                          title="<?= htmlspecialchars($p['colorNames'][$i] ?? '') ?>"></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="product-actions">
                    <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-primary">查看</a>
                    <form action="add_compare.php" method="POST" style="flex:1;">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-outline">比較</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
