<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../db.php';

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
            'id'        => $row['id'],
            'brand'     => $row['brand']     ?? '',
            'name'      => $row['name']      ?? '',
            'purpose'   => $row['purpose']   ?? '',
            'image_url' => $row['image_url'] ?? '',
            'category'  => $row['category']  ?? '',
            'score'     => round($score, 1),
            'reasons'   => array_values(array_unique($reasons)),
            'colorNames'=> $colorNames,
            'colorHexes'=> $colorHexes,
        ];
    }

    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
    $products = array_slice($ranked, 0, 8);
}

// ── 天氣推薦產品（控油／持妝，高溫時顯示） ───────────────────────
$heatProducts = [];
if (isset($pdo)) {
    try {
        $heatKw = ['控油', '持妝', '定妝', '抗汗', '霧面', '長效'];
        $conditions = array_map(fn($k) => "purpose LIKE :kw_p_$k OR name LIKE :kw_n_$k", array_keys($heatKw));
        $sql = "SELECT id, name, brand, purpose, image_url FROM data WHERE category = '底妝' AND (" . implode(' OR ', $conditions) . ") LIMIT 20";
        $st = $pdo->prepare($sql);
        foreach ($heatKw as $i => $kw) {
            $st->bindValue(":kw_p_$i", '%' . $kw . '%');
            $st->bindValue(":kw_n_$i", '%' . $kw . '%');
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        usort($rows, function($a, $b) use ($heatKw) {
            $scoreA = array_sum(array_map(fn($k) => (mb_strpos($a['purpose'].$a['name'], $k) !== false) ? 1 : 0, $heatKw));
            $scoreB = array_sum(array_map(fn($k) => (mb_strpos($b['purpose'].$b['name'], $k) !== false) ? 1 : 0, $heatKw));
            return $scoreB <=> $scoreA;
        });
        $heatProducts = array_slice($rows, 0, 4);
    } catch (Exception $e) {}
}

// ── 天氣推薦產品（保濕／滋潤，低溫時顯示） ───────────────────────
$coldProducts = [];
if (isset($pdo)) {
    try {
        $coldKw = ['保濕', '滋潤', '水光', '養膚', '補水', '透亮'];
        $conditions = array_map(fn($k) => "purpose LIKE :kw_p_$k OR name LIKE :kw_n_$k", array_keys($coldKw));
        $sql = "SELECT id, name, brand, purpose, image_url FROM data WHERE category = '底妝' AND (" . implode(' OR ', $conditions) . ") LIMIT 20";
        $st = $pdo->prepare($sql);
        foreach ($coldKw as $i => $kw) {
            $st->bindValue(":kw_p_$i", '%' . $kw . '%');
            $st->bindValue(":kw_n_$i", '%' . $kw . '%');
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        usort($rows, function($a, $b) use ($coldKw) {
            $scoreA = array_sum(array_map(fn($k) => (mb_strpos($a['purpose'].$a['name'], $k) !== false) ? 1 : 0, $coldKw));
            $scoreB = array_sum(array_map(fn($k) => (mb_strpos($b['purpose'].$b['name'], $k) !== false) ? 1 : 0, $coldKw));
            return $scoreB <=> $scoreA;
        });
        $coldProducts = array_slice($rows, 0, 4);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>COSMETIC — AI 專屬推薦</title>
    <style>
        .products-wrap { max-width: var(--max-w); margin: 24px auto 60px; padding: 0 24px; }
        @media(max-width:640px){
          .products-wrap { padding: 0 14px; margin-top: 16px; }
          .ai-profile-card { padding: 20px 18px; }
          .section-head { flex-direction: column; gap: 4px; }
        }
        .ai-profile-card {
            background: linear-gradient(135deg, #6b2d3e 0%, #c26b7c 100%);
            border: 1px solid #c26b7c;
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 32px;
            box-shadow: 0 4px 20px rgba(107,45,62,.25);
        }
        .ai-profile-card h2 {
            font-size: 20px;
            font-weight: 700;
            color: #fff;
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
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.3);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 13px;
            color: #fff;
            font-weight: 600;
            box-shadow: none;
        }
        .profile-tag .tag-label {
            color: rgba(255,255,255,.6);
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
            background: linear-gradient(135deg, #6b2d3e, #c26b7c);
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
            background: #fff;
            color: #6b2d3e;
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

<?php include __DIR__ . '/../header.php'; ?>

<main class="page">
<div class="products-wrap">

<?php if (!isset($_SESSION['user'])): ?>
    <!-- Not logged in -->
    <div class="ai-empty-state">
        <div class="icon">✨</div>
        <h3>登入後查看 AI 專屬推薦</h3>
        <p>完成 AI 膚質分析後，系統將根據您的膚質、膚色和妝感偏好，精選最適合您的底妝產品。</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/首頁/login.php" class="ai-cta-btn ai-cta-btn-large">前往登入</a>
            <a href="<?= BASE_URL ?>/AI/index.php" class="ai-cta-btn ai-cta-btn-large"
               style="background:linear-gradient(135deg,#6b2d3e,#c26b7c);">去做 AI 分析</a>
        </div>
    </div>

<?php elseif (!$hasProfile): ?>
    <!-- Logged in but no AI profile -->
    <div class="ai-empty-state">
        <div class="icon">🔍</div>
        <h3>尚未完成 AI 膚質分析</h3>
        <p>完成分析後，系統會依照您的膚質、膚色與妝感偏好，為您精選最合適的底妝產品。</p>
        <a href="<?= BASE_URL ?>/AI/index.php" class="ai-cta-btn ai-cta-btn-large">開始 AI 膚質分析</a>
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
            <a href="<?= BASE_URL ?>/AI/index.php" class="ai-cta-btn">重新 AI 檢測</a>
            <a href="<?= BASE_URL ?>/首頁/profile.php"
               style="font-size:13px;color:rgba(255,255,255,.65);text-decoration:none;">編輯個人資料 →</a>
        </div>
    </div>

    <!-- 地區選擇 -->
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; padding:10px 16px; background:#fdf2f4; border:1px solid #f5c6d0; border-radius:14px;">
        <span style="font-size:12px; color:#c26b7c; font-weight:600; white-space:nowrap;">天氣地區</span>
        <select id="city-select" style="flex:1; border:1.5px solid #f5c6d0; border-radius:8px; padding:5px 10px; font-size:13px; color:#6b2d3e; background:white; outline:none; cursor:pointer;">
            <option value="auto">自動偵測位置</option>
            <optgroup label="六都">
                <option value="25.0330,121.5654">台北市</option>
                <option value="25.0169,121.4627">新北市</option>
                <option value="24.9937,121.3010">桃園市</option>
                <option value="24.1477,120.6736">台中市</option>
                <option value="22.9999,120.2269">台南市</option>
                <option value="22.6273,120.3014">高雄市</option>
            </optgroup>
            <optgroup label="其他縣市">
                <option value="25.1276,121.7392">基隆市</option>
                <option value="24.8066,120.9686">新竹市</option>
                <option value="24.5636,120.8214">苗栗縣</option>
                <option value="24.0518,120.5161">彰化縣</option>
                <option value="23.9610,120.9718">南投縣</option>
                <option value="23.7224,120.4313">雲林縣</option>
                <option value="23.4800,120.4491">嘉義市</option>
                <option value="22.6760,120.4930">屏東縣</option>
                <option value="24.7021,121.7377">宜蘭縣</option>
                <option value="23.9871,121.6015">花蓮縣</option>
                <option value="22.7583,121.1444">台東縣</option>
                <option value="23.5654,119.5795">澎湖縣</option>
            </optgroup>
        </select>
        <span id="weather-status" style="font-size:11px; color:#c09aaa; white-space:nowrap;"></span>
    </div>

    <!-- 抗汗指南（氣溫超過門檻時由 JS 顯示） -->
    <div id="heat-alert" style="display:none; margin-bottom:28px; border-radius:20px; overflow:hidden; border:1px solid #6b2d3e; box-shadow:0 4px 20px rgba(61,21,32,.25);">

        <!-- 標題列 -->
        <div style="background:linear-gradient(135deg,#3d1520,#6b2d3e); padding:18px 24px; display:flex; align-items:center; gap:14px;">
            <div style="flex:1;">
                <div style="font-size:15px; font-weight:700; color:#fff;">今日高溫提醒</div>
                <div style="font-size:12px; color:rgba(255,255,255,.7); margin-top:3px;">今日最高氣溫 <span id="heat-temp-text" style="font-weight:700; color:#f9cfd8;"></span>，建議加上定妝步驟讓妝感撐一整天</div>
            </div>
            <div style="background:rgba(255,255,255,.15); border:1.5px solid rgba(255,255,255,.3); border-radius:14px; padding:8px 16px; text-align:center;">
                <div id="heat-temp-num" style="font-size:20px; font-weight:800; color:#fff; line-height:1;"></div>
                <div style="font-size:10px; color:rgba(255,255,255,.6); margin-top:2px;">今日最高</div>
            </div>
        </div>

        <!-- 內容區 -->
        <div style="background:#fdf2f4; padding:20px 24px;">

            <!-- 三明治定妝法 -->
            <div style="font-size:13px; font-weight:700; color:#6b2d3e; margin-bottom:12px;">三明治定妝法（新手 3 步驟）</div>
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px;">
                <?php
                $steps = [
                    ['num'=>'1','title'=>'散粉打底',    'desc'=>'打完底妝後用大刷輕掃蜂巢散粉，吸走多餘油脂'],
                    ['num'=>'2','title'=>'噴定妝噴霧',  'desc'=>'距臉 20cm 以 Z 字形均勻噴灑，等 30 秒自然乾'],
                    ['num'=>'3','title'=>'再掃一層散粉', 'desc'=>'鎖住噴霧，三明治順序讓持妝效果翻倍'],
                ];
                foreach ($steps as $s): ?>
                <div style="background:#fff; border-radius:14px; padding:14px 12px; border:1px solid #f5c6d0;">
                    <div style="width:26px; height:26px; border-radius:50%; background:#c26b7c; color:#fff; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; margin-bottom:8px;"><?= $s['num'] ?></div>
                    <div style="font-size:13px; font-weight:700; color:#6b2d3e; margin-bottom:4px;"><?= $s['title'] ?></div>
                    <div style="font-size:11px; color:#c09aaa; line-height:1.6;"><?= $s['desc'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 小提醒 -->
            <div style="background:#fff; border:1px solid #f5c6d0; border-radius:12px; padding:10px 16px; font-size:12px; color:#6b2d3e; margin-bottom:20px;">
                出門前最後一步才噴，隨身帶一瓶定妝噴霧，中午直接噴臉補妝，不用補粉也能維持妝感。
            </div>

            <?php if (!empty($heatProducts)): ?>
            <!-- 天氣推薦產品 -->
            <div style="font-size:13px; font-weight:700; color:#6b2d3e; margin-bottom:12px;">適合今天高溫的持妝產品</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px;">
                <?php foreach ($heatProducts as $hp): ?>
                <a href="product.php?id=<?= $hp['id'] ?>" style="text-decoration:none; background:#fff; border-radius:16px; border:1.5px solid #f5c6d0; overflow:hidden; display:block; transition:box-shadow .2s, transform .2s;" onmouseover="this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <div style="width:100%; aspect-ratio:1; background:#fdf2f4; overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:32px;">
                        <?php if (!empty($hp['image_url'])): ?>
                        <img src="<?= htmlspecialchars($hp['image_url']) ?>"
                             alt="<?= htmlspecialchars($hp['name']) ?>"
                             style="width:100%; height:100%; object-fit:cover;"
                             onerror="this.parentElement.innerHTML='💄';">
                        <?php else: ?>💄<?php endif; ?>
                    </div>
                    <div style="padding:10px 12px;">
                        <div style="font-size:10px; color:#c09aaa; font-weight:600; margin-bottom:3px;"><?= htmlspecialchars($hp['brand']) ?></div>
                        <div style="font-size:12px; color:#333; font-weight:700; line-height:1.4;"><?= htmlspecialchars($hp['name']) ?></div>
                        <div style="margin-top:5px; font-size:11px; color:#c09aaa; line-height:1.4;"><?= htmlspecialchars(mb_substr($hp['purpose'], 0, 18)) ?>…</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 乾冷提醒（氣溫低於門檻時由 JS 顯示） -->
    <div id="cold-alert" style="display:none; margin-bottom:28px; border-radius:20px; overflow:hidden; border:1px solid #f5c6d0; box-shadow:0 2px 12px rgba(0,0,0,.07);">

        <!-- 標題列 -->
        <div style="background:linear-gradient(135deg,#edf3f9,#e8ecf5); padding:18px 24px; display:flex; align-items:center; gap:14px; border-bottom:1px solid #d8e2ed;">
            <div style="flex:1;">
                <div style="font-size:15px; font-weight:700; color:#3a4f6a;">今日低溫提醒</div>
                <div style="font-size:12px; color:#8a9bbf; margin-top:3px;">今日最高氣溫 <span id="cold-temp-text" style="font-weight:700; color:#5a7299;"></span>，天氣乾冷，妝前補水是重點</div>
            </div>
            <div style="background:white; border:1.5px solid #c8d8ea; border-radius:14px; padding:8px 16px; text-align:center; box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <div id="cold-temp-num" style="font-size:20px; font-weight:800; color:#3a4f6a; line-height:1;"></div>
                <div style="font-size:10px; color:#8a9bbf; margin-top:2px;">今日最高</div>
            </div>
        </div>

        <!-- 內容區 -->
        <div style="background:white; padding:20px 24px;">

            <!-- 保濕上妝法 -->
            <div style="font-size:13px; font-weight:700; color:#3a4f6a; margin-bottom:12px;">保濕上妝法（3 步驟不卡粉）</div>
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px;">
                <?php
                $coldSteps = [
                    ['num'=>'1','title'=>'妝前敷水膜',    'desc'=>'上妝前敷 5 分鐘保濕面膜，讓肌膚充飽水分'],
                    ['num'=>'2','title'=>'保濕妝前乳打底', 'desc'=>'選含玻尿酸的妝前乳，填平紋路防浮粉卡紋'],
                    ['num'=>'3','title'=>'海綿輕拍上妝',   'desc'=>'用海綿輕拍取代刷塗，避免破壞肌膚保濕層'],
                ];
                foreach ($coldSteps as $s): ?>
                <div style="background:#edf3f9; border-radius:14px; padding:14px 12px; border:1px solid #d8e2ed;">
                    <div style="width:26px; height:26px; border-radius:50%; background:#b8ccdf; color:#3a4f6a; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; margin-bottom:8px;"><?= $s['num'] ?></div>
                    <div style="font-size:13px; font-weight:700; color:#3a4f6a; margin-bottom:4px;"><?= $s['title'] ?></div>
                    <div style="font-size:11px; color:#8a9bbf; line-height:1.6;"><?= $s['desc'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 小提醒 -->
            <div style="background:#edf3f9; border:1px solid #d8e2ed; border-radius:12px; padding:10px 16px; font-size:12px; color:#5a7299; margin-bottom:20px;">
                脫妝時先噴保濕噴霧補水再補妝，乾燥天不要直接補粉，否則會讓浮粉更嚴重。
            </div>

            <?php if (!empty($coldProducts)): ?>
            <!-- 冷天推薦產品 -->
            <div style="font-size:13px; font-weight:700; color:#3a4f6a; margin-bottom:12px;">適合今天低溫的保濕底妝</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px;">
                <?php foreach ($coldProducts as $cp): ?>
                <a href="product.php?id=<?= $cp['id'] ?>" style="text-decoration:none; background:white; border-radius:16px; border:1.5px solid #d8e2ed; overflow:hidden; display:block; transition:box-shadow .2s, transform .2s;" onmouseover="this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <div style="width:100%; aspect-ratio:1; background:#edf3f9; overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:32px;">
                        <?php if (!empty($cp['image_url'])): ?>
                        <img src="<?= htmlspecialchars($cp['image_url']) ?>"
                             alt="<?= htmlspecialchars($cp['name']) ?>"
                             style="width:100%; height:100%; object-fit:cover;"
                             onerror="this.parentElement.innerHTML='💄';">
                        <?php else: ?>💄<?php endif; ?>
                    </div>
                    <div style="padding:10px 12px;">
                        <div style="font-size:10px; color:#8a9bbf; font-weight:600; margin-bottom:3px;"><?= htmlspecialchars($cp['brand']) ?></div>
                        <div style="font-size:12px; color:#333; font-weight:700; line-height:1.4;"><?= htmlspecialchars($cp['name']) ?></div>
                        <div style="margin-top:5px; font-size:11px; color:#8a9bbf; line-height:1.4;"><?= htmlspecialchars(mb_substr($cp['purpose'], 0, 18)) ?>…</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>


<?php endif; ?>
</div>
</main>

<?php include 'footer.php'; ?>

<script>
(function () {
    const HOT_THRESHOLD  = 25;
    const COLD_THRESHOLD = 18;
    const STORAGE_KEY = 'skinmatch_city';

    const select    = document.getElementById('city-select');
    const status    = document.getElementById('weather-status');
    const alert     = document.getElementById('heat-alert');
    const tempNum   = document.getElementById('heat-temp-num');
    const tempTxt   = document.getElementById('heat-temp-text');
    const coldAlert = document.getElementById('cold-alert');
    const coldNum   = document.getElementById('cold-temp-num');
    const coldTxt   = document.getElementById('cold-temp-text');

    function showAlert(tempC) {
        if (!alert) return;
        if (tempNum) tempNum.textContent = tempC + '°C';
        if (tempTxt) tempTxt.textContent = tempC + '°C';
        alert.style.display = 'block';
    }

    function hideAlert() {
        if (alert) alert.style.display = 'none';
    }

    function showColdAlert(tempC) {
        if (!coldAlert) return;
        if (coldNum) coldNum.textContent = tempC + '°C';
        if (coldTxt) coldTxt.textContent = tempC + '°C';
        coldAlert.style.display = 'block';
    }

    function hideColdAlert() {
        if (coldAlert) coldAlert.style.display = 'none';
    }

    function setStatus(msg) {
        if (status) status.textContent = msg;
    }

    async function fetchMaxTemp(lat, lon) {
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&daily=temperature_2m_max&timezone=Asia/Taipei&forecast_days=1`;
        const res = await fetch(url);
        if (!res.ok) return null;
        const data = await res.json();
        return data?.daily?.temperature_2m_max?.[0] ?? null;
    }

    async function checkWeather(lat, lon) {
        setStatus('查詢中...');
        const temp = await fetchMaxTemp(lat, lon).catch(() => null);
        if (temp === null) { setStatus('無法取得天氣'); return; }
        setStatus('');
        const t = Math.round(temp);
        if (temp > HOT_THRESHOLD) {
            showAlert(t);
            hideColdAlert();
        } else if (temp < COLD_THRESHOLD) {
            hideAlert();
            showColdAlert(t);
        } else {
            hideAlert();
            hideColdAlert();
        }
    }

    function runAuto() {
        if (!navigator.geolocation) { setStatus('不支援定位'); return; }
        setStatus('定位中...');
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => checkWeather(coords.latitude, coords.longitude),
            (err) => {
                if (err.code === 1) {
                    setStatus('定位被拒絕，請手動選擇地區');
                } else {
                    setStatus('定位失敗，請手動選擇地區');
                }
            },
            { timeout: 10000, maximumAge: 300000 }
        );
    }

    function onCityChange() {
        const val = select.value;
        localStorage.setItem(STORAGE_KEY, val);
        if (val === 'auto') {
            runAuto();
        } else {
            const [lat, lon] = val.split(',');
            checkWeather(parseFloat(lat), parseFloat(lon));
        }
    }

    // 還原上次選擇
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        const opt = select.querySelector(`option[value="${saved}"]`);
        if (opt) select.value = saved;
    }

    select.addEventListener('change', onCityChange);

    // 初始執行：auto 需等使用者主動點擊才觸發定位（iOS Safari 限制）
    if (select.value === 'auto') {
        setStatus('📍 點擊以偵測位置');
        status.style.cursor = 'pointer';
        status.style.textDecoration = 'underline';
        status.addEventListener('click', function handler() {
            status.style.cursor = '';
            status.style.textDecoration = '';
            status.removeEventListener('click', handler);
            runAuto();
        }, { once: true });
    } else {
        const [lat, lon] = select.value.split(',');
        checkWeather(parseFloat(lat), parseFloat(lon));
    }
})();
</script>
</body>
</html>
