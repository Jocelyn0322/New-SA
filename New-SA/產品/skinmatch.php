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
        '水光感'  => ['水光','保濕','潤澤','光澤','水感'],
        '自然光澤' => ['自然','裸妝','輕薄','日常'],
    ];
    $styleKw = [
        '日常通勤' => ['輕薄','自然','日常','裸妝'],
        '韓系清透' => ['保濕','裸妝','輕薄','水光'],
        '歐美立體' => ['持妝','遮瑕','高遮瑕','長效'],
        '約會精緻' => ['光澤','持妝','緞面','長效'],
    ];
    $sensitiveKw = ['無香料','無酒精','溫和','舒敏','低刺激','敏感肌','保濕','養膚','修護'];

    try {
        $stmt = $pdo->query(
            "SELECT d.*,
                    po.name AS origin,
                    GROUP_CONCAT(DISTINCT i.name ORDER BY i.name SEPARATOR '、') AS ingredients,
                    GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.color_id SEPARATOR ',') AS color_names,
                    GROUP_CONCAT(DISTINCT pc.color_hex  ORDER BY pc.color_id SEPARATOR ',') AS color_hexes
             FROM data d
             LEFT JOIN product_origins po ON d.origin_id = po.id
             LEFT JOIN product_ingredients pi ON d.id = pi.product_id
             LEFT JOIN ingredients i ON pi.ingredient_id = i.id
             LEFT JOIN product_colors pc ON d.id = pc.p_id
             WHERE d.category = '底妝'
             GROUP BY d.id
             ORDER BY d.created_at DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }

    $skw   = $skinTypeKw[$skinType] ?? $skinTypeKw['中性皮'];
    $stykw = $styleKw[$makeupStyle] ?? [];

    $dryTypes  = ['乾性皮', '混乾皮'];
    $oilyTypes = ['油性皮', '混油皮'];
    $rawFkw    = $finishKw[$makeupFinish] ?? [];

    if (in_array($skinType, $dryTypes) && $makeupFinish === '霧面') {
        // 乾皮 + 霧面：移除控油/持妝，保留柔霧/輕薄，補保濕
        $oilConflictKw = ['控油', '無油', '持妝', '抗汗', '長效'];
        $fkw = array_merge(
            array_filter($rawFkw, fn($k) => !in_array($k, $oilConflictKw)),
            ['保濕', '潤澤']
        );
    } elseif (in_array($skinType, $oilyTypes) && $makeupFinish === '水光') {
        // 油皮 + 水光：使用者明確選水光，直接用水光關鍵字
        // 控油由 pureSkinKw 次要加分，不蓋過水光主訴求
        $fkw = $rawFkw; // ['水光','保濕','潤澤','光澤','水感']
    } else {
        $fkw = $rawFkw;
    }

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

// ── 膚質關鍵字對照（天氣推薦個人化用） ────────────────────────────
$_skinKwMap = [
    '乾性皮' => ['保濕','潤澤','養膚','滋潤','水光'],
    '混乾皮' => ['保濕','潤澤','輕薄','服貼'],
    '油性皮' => ['控油','霧面','柔霧','無油','抗汗'],
    '混油皮' => ['控油','霧面','平衡','輕薄'],
    '中性皮' => ['自然','輕薄','通用'],
    '敏感肌' => ['溫和','舒敏','無香料','低刺激'],
];
$_userSkinKw = $_skinKwMap[$profile['skin_type'] ?? ''] ?? [];

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
        usort($rows, function($a, $b) use ($heatKw, $_userSkinKw) {
            $tA = $a['purpose'] . $a['name'];
            $tB = $b['purpose'] . $b['name'];
            $wA = array_sum(array_map(fn($k) => mb_strpos($tA, $k) !== false ? 2 : 0, $heatKw));
            $sA = array_sum(array_map(fn($k) => mb_strpos($tA, $k) !== false ? 1 : 0, $_userSkinKw));
            $wB = array_sum(array_map(fn($k) => mb_strpos($tB, $k) !== false ? 2 : 0, $heatKw));
            $sB = array_sum(array_map(fn($k) => mb_strpos($tB, $k) !== false ? 1 : 0, $_userSkinKw));
            return ($wB + $sB) <=> ($wA + $sA);
        });
        $heatProducts = array_slice($rows, 0, 4);
    } catch (Exception $e) {}
}

// ── AI 關鍵字（依膚質＋妝感偏好，結果快取於 session） ───────────
function getAIProductKeywords(string $skinType, string $makeupFinish, bool $sensitive): array {
    $cacheKey = 'ai_kw_' . md5($skinType . $makeupFinish . ($sensitive ? '1' : '0'));
    if (!empty($_SESSION[$cacheKey])) return $_SESSION[$cacheKey];

    $groqKey  = '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';
    $note     = $sensitive ? '，且皮膚敏感' : '';
    $prompt   = "用戶膚質：{$skinType}{$note}，妝感偏好：{$makeupFinish}。請推薦最適合此用戶的粉底液特性關鍵字（繁體中文），只輸出5-7個關鍵字以逗號分隔，不要其他說明。";

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'       => 'llama-3.3-70b-versatile',
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => 60,
            'temperature' => 0.1,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $groqKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 4,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return [];
    $content  = trim(json_decode($resp, true)['choices'][0]['message']['content'] ?? '');
    $keywords = array_values(array_filter(array_map('trim', explode(',', $content))));
    $_SESSION[$cacheKey] = $keywords;
    return $keywords;
}

// ── 天氣推薦產品（輕薄日常，舒適溫度時顯示） ─────────────────────
$mildProducts = [];
if (isset($pdo)) {
    try {
        // 妝感關鍵字（使用者選擇，主要，權重 2）
        // 膚質關鍵字去掉妝感相關詞（次要，權重 1），避免油皮「霧面」蓋過使用者選的「水光」
        $finishRelatedKw = ['霧面','柔霧','水光','裸光','光澤','光感','緞面','霧感'];
        $pureSkinKw = array_values(array_filter(
            $_userSkinKw ?: [],
            fn($k) => !in_array($k, $finishRelatedKw)
        ));
        $scoreKw = array_merge(
            array_fill_keys($fkw ?: ['輕薄','自然'], 2),
            array_fill_keys($pureSkinKw ?: [], 1)
        );

        $st = $pdo->query("SELECT id, name, brand, purpose, image_url FROM data WHERE category = '底妝' ORDER BY id ASC");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // 計算每個產品的分數並存入
        foreach ($rows as &$row) {
            $t = $row['purpose'] . ' ' . $row['name'];
            $row['_score'] = array_sum(array_map(
                fn($kw, $w) => mb_strpos($t, $kw, 0, 'UTF-8') !== false ? $w : 0,
                array_keys($scoreKw), $scoreKw
            ));
        }
        unset($row);

        // 由高到低排序
        usort($rows, fn($a, $b) => $b['_score'] <=> $a['_score']);

        // 只取有得分的，最多 20 個，隨機選 4 個
        $scored = array_values(array_filter($rows, fn($r) => $r['_score'] > 0));
        $pool   = array_slice(!empty($scored) ? $scored : $rows, 0, 20);
        shuffle($pool);
        $mildProducts = array_slice($pool, 0, 4);
    } catch (Exception $e) {}
}

// ── 色號推薦：找最接近使用者膚色的色號 ──────────────────────────
function hexColorDistance(string $hex1, string $hex2): float {
    $parse = fn($h) => [
        hexdec(substr(ltrim($h,'#'), 0, 2)),
        hexdec(substr(ltrim($h,'#'), 2, 2)),
        hexdec(substr(ltrim($h,'#'), 4, 2)),
    ];
    [$r1,$g1,$b1] = $parse($hex1);
    [$r2,$g2,$b2] = $parse($hex2);
    return sqrt(($r1-$r2)**2 + ($g1-$g2)**2 + ($b1-$b2)**2);
}

if (!empty($mildProducts) && !empty($toneHex)) {
    try {
        $pids = array_column($mildProducts, 'id');
        $ph   = implode(',', array_fill(0, count($pids), '?'));
        $cs   = $pdo->prepare("SELECT p_id, color_name, color_hex FROM product_colors WHERE p_id IN ($ph) ORDER BY p_id, color_id");
        $cs->execute($pids);
        $colorsByProduct = [];
        foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $colorsByProduct[$c['p_id']][] = $c;
        }
        foreach ($mildProducts as &$mp) {
            $shades = $colorsByProduct[$mp['id']] ?? [];
            if (empty($shades)) { $mp['shade'] = null; continue; }
            $best = $shades[0]; $bestDist = PHP_FLOAT_MAX;
            foreach ($shades as $shade) {
                $d = hexColorDistance($toneHex, $shade['color_hex']);
                if ($d < $bestDist) { $bestDist = $d; $best = $shade; }
            }
            $mp['shade'] = $best;
        }
        unset($mp);
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
        usort($rows, function($a, $b) use ($coldKw, $_userSkinKw) {
            $tA = $a['purpose'] . $a['name'];
            $tB = $b['purpose'] . $b['name'];
            $wA = array_sum(array_map(fn($k) => mb_strpos($tA, $k) !== false ? 2 : 0, $coldKw));
            $sA = array_sum(array_map(fn($k) => mb_strpos($tA, $k) !== false ? 1 : 0, $_userSkinKw));
            $wB = array_sum(array_map(fn($k) => mb_strpos($tB, $k) !== false ? 2 : 0, $coldKw));
            $sB = array_sum(array_map(fn($k) => mb_strpos($tB, $k) !== false ? 1 : 0, $_userSkinKw));
            return ($wB + $sB) <=> ($wA + $sA);
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
        .makeup-pill {
            padding: 7px 16px;
            border-radius: 24px;
            border: 1.5px solid rgba(255,255,255,.4);
            background: rgba(255,255,255,.1);
            color: rgba(255,255,255,.85);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all .15s;
            font-family: inherit;
        }
        .makeup-pill:hover {
            background: rgba(255,255,255,.2);
            border-color: rgba(255,255,255,.7);
        }
        .makeup-pill--active {
            background: #fff;
            border-color: #fff;
            color: #6b2d3e;
            font-weight: 700;
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
                <span id="profileToneDot" class="tone-dot" style="background:<?= htmlspecialchars($toneHex) ?>;"></span>
                <span id="profileToneLabel"><?= htmlspecialchars($skinTone) ?></span>
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
                <span id="profileFinishLabel"><?= htmlspecialchars($makeupFinish) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($makeupStyle): ?>
            <div class="profile-tag">
                <span class="tag-label">妝容風格</span>
                <span id="profileStyleLabel"><?= htmlspecialchars($makeupStyle) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- 妝容快速編輯面板 -->
        <div id="makeupEditPanel" style="display:none;background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.25);border-radius:16px;padding:16px 18px;margin-bottom:16px;">
            <p style="font-size:11px;color:rgba(255,255,255,.65);margin:0 0 10px;font-weight:600;letter-spacing:.04em;">妝感偏好</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                <?php foreach (['霧面','水光感','自然光澤'] as $f): ?>
                <button type="button"
                    onclick="selectMakeupOption('finish','<?= $f ?>')"
                    data-finish="<?= $f ?>"
                    class="makeup-pill <?= $makeupFinish === $f ? 'makeup-pill--active' : '' ?>">
                    <?= $f ?>
                </button>
                <?php endforeach; ?>
            </div>
            <p style="font-size:11px;color:rgba(255,255,255,.65);margin:0 0 10px;font-weight:600;letter-spacing:.04em;">妝容風格偏好</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                <?php foreach (['日常通勤','韓系清透','歐美立體','約會精緻'] as $s): ?>
                <button type="button"
                    onclick="selectMakeupOption('style','<?= $s ?>')"
                    data-style="<?= $s ?>"
                    class="makeup-pill <?= $makeupStyle === $s ? 'makeup-pill--active' : '' ?>">
                    <?= $s ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <button onclick="saveMakeupPrefs()" style="padding:8px 20px;border-radius:24px;border:none;background:#fff;color:#6b2d3e;font-size:13px;font-weight:700;cursor:pointer;">儲存</button>
                <button onclick="document.getElementById('makeupEditPanel').style.display='none'" style="padding:8px 14px;border-radius:24px;border:1.5px solid rgba(255,255,255,.4);background:transparent;color:rgba(255,255,255,.7);font-size:13px;cursor:pointer;">取消</button>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/AI/index.php" class="ai-cta-btn">重新 AI 檢測</a>
            <button onclick="openSwatchModal()" style="padding:9px 16px;border-radius:24px;border:1.5px solid rgba(255,255,255,.5);background:rgba(255,255,255,.12);color:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;" onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">🎨 色卡</button>
            <button onclick="toggleMakeupEdit()" style="padding:9px 16px;border-radius:24px;border:1.5px solid rgba(255,255,255,.5);background:rgba(255,255,255,.12);color:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;" onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">✏️ 更改妝容</button>
            <a href="<?= BASE_URL ?>/首頁/profile.php"
               style="font-size:13px;color:rgba(255,255,255,.55);text-decoration:none;">編輯個人資料 →</a>
        </div>
    </div>

    <!-- 色卡 Modal -->
    <div id="swatchModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);justify-content:center;align-items:center;">
        <div style="background:#fff;border-radius:20px;padding:24px;max-width:500px;width:92%;max-height:80vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.2);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <h3 style="margin:0;font-size:17px;font-weight:800;color:#3d1520;">🎨 膚色色卡對照</h3>
                <button onclick="closeSwatchModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#aaa;padding:0 4px;">✕</button>
            </div>
            <p style="font-size:12px;color:#9b7b84;margin:0 0 16px;">點選色卡可更換你的膚色，選完會自動儲存</p>
            <div id="swatchGroups" style="display:flex;flex-direction:column;gap:16px;">
                <p style="color:#aaa;font-size:13px;text-align:center;">載入中...</p>
            </div>
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

            <?php
            $dryTypes = ['乾性皮', '混乾皮'];
            $oilyTypes = ['油性皮', '混油皮'];
            $currentSkinType = $profile['skin_type'] ?? '';

            if (in_array($currentSkinType, $dryTypes)) {
                $methodTitle = '補水定妝法'; $methodSub = '不卡紋 3 步驟';
                $steps = [
                    ['icon'=>'💧','num'=>'1','title'=>'妝前補水',   'desc'=>'上妝前敷保濕面膜或噴保濕噴霧，讓肌膚充飽水分再上妝'],
                    ['icon'=>'🧴','num'=>'2','title'=>'保濕妝前乳', 'desc'=>'含玻尿酸的妝前乳打底，填平乾紋防浮粉卡紋'],
                    ['icon'=>'🪄','num'=>'3','title'=>'海綿輕拍',   'desc'=>'用海綿輕拍取代刷塗，鎖住水分同時讓底妝更服帖'],
                ];
                $tip = '散粉只需少量輕掃 T 字，用量過多會讓乾皮更顯卡粉，保濕噴霧才是你的最佳補妝工具。';
            } elseif ($currentSkinType === '敏感肌' || $isSensitive) {
                $methodTitle = '溫和上妝法'; $methodSub = '低刺激 3 步驟';
                $steps = [
                    ['icon'=>'🌿','num'=>'1','title'=>'鎮靜打底',    'desc'=>'上妝前用溫和化妝水輕拍臉部，靜待 5 分鐘讓肌膚穩定'],
                    ['icon'=>'✨','num'=>'2','title'=>'礦物粉底',    'desc'=>'選無香料、無酒精的礦物粉底，以海綿輕柔按壓避免摩擦'],
                    ['icon'=>'🪶','num'=>'3','title'=>'礦物散粉定妝','desc'=>'用礦物蜜粉輕掃定妝，刷具接觸肌膚次數越少越好'],
                ];
                $tip = '補妝時先用吸油紙輕壓，再用礦物粉輕拍，避免刷具反覆摩擦造成泛紅。';
            } else {
                $methodTitle = '三明治定妝法'; $methodSub = '新手 3 步驟';
                $steps = [
                    ['icon'=>'🖌️','num'=>'1','title'=>'散粉打底',    'desc'=>'打完底妝後用大刷輕掃蜂巢散粉，吸走多餘油脂'],
                    ['icon'=>'💨','num'=>'2','title'=>'噴定妝噴霧',  'desc'=>'距臉 20cm 以 Z 字形均勻噴灑，等 30 秒自然乾'],
                    ['icon'=>'✨','num'=>'3','title'=>'再掃一層散粉', 'desc'=>'鎖住噴霧，三明治順序讓持妝效果翻倍'],
                ];
                $tip = '出門前最後一步才噴，隨身帶一瓶定妝噴霧，中午直接噴臉補妝，不用補粉也能維持妝感。';
            }
            ?>
            <!-- 定妝法標題 -->
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <div style="font-size:16px;font-weight:800;color:#3d1520;"><?= htmlspecialchars($methodTitle) ?></div>
                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:rgba(107,45,62,.12);color:#6b2d3e;"><?= htmlspecialchars($methodSub) ?></span>
            </div>

            <!-- 橫向大卡 -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px;">
                <?php
                $bgs = [
                    'linear-gradient(145deg,#fff5f7,#fce8ed)',
                    'linear-gradient(145deg,#fce8ed,#f8dde4)',
                    'linear-gradient(145deg,#6b2d3e,#9d3f55)',
                ];
                foreach ($steps as $i => $s):
                    $isDark = $i === 2;
                    $textColor    = $isDark ? '#fff' : '#3d1520';
                    $subColor     = $isDark ? 'rgba(255,255,255,.5)' : '#c09aaa';
                    $descColor    = $isDark ? 'rgba(255,255,255,.7)' : '#b08090';
                    $borderStyle  = $isDark ? 'transparent' : '#f5c6d0';
                ?>
                <div style="background:<?= $bgs[$i] ?>;border-radius:20px;padding:22px 18px;border:1.5px solid <?= $borderStyle ?>;box-shadow:0 2px 12px rgba(107,45,62,.1);">
                    <div style="font-size:32px;margin-bottom:12px;"><?= $s['icon'] ?></div>
                    <div style="font-size:10px;font-weight:700;color:<?= $subColor ?>;letter-spacing:.1em;margin-bottom:5px;">STEP <?= $i+1 ?></div>
                    <div style="font-size:15px;font-weight:800;color:<?= $textColor ?>;margin-bottom:8px;"><?= $s['title'] ?></div>
                    <div style="font-size:11px;color:<?= $descColor ?>;line-height:1.7;"><?= $s['desc'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 小提醒 -->
            <div style="display:flex;gap:12px;align-items:flex-start;background:rgba(107,45,62,.06);border-left:3px solid #c26b7c;border-radius:0 12px 12px 0;padding:12px 16px;margin-bottom:20px;">
                <span style="font-size:16px;flex-shrink:0;margin-top:1px;">💡</span>
                <span style="font-size:12px;color:#6b2d3e;line-height:1.7;"><?= htmlspecialchars($tip) ?></span>
            </div>

            <?php if (!empty($heatProducts)): ?>
            <!-- 天氣推薦產品 -->
            <div style="font-size:13px; font-weight:700; color:#6b2d3e; margin-bottom:12px;">適合今天高溫的持妝產品</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; align-items:stretch;">
                <?php foreach ($heatProducts as $hp): ?>
                <a href="product.php?id=<?= $hp['id'] ?>" style="text-decoration:none; background:#fff; border-radius:16px; border:1.5px solid #f5c6d0; overflow:hidden; display:flex; flex-direction:column; height:100%; transition:box-shadow .2s, transform .2s;" onmouseover="this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <div style="width:100%; aspect-ratio:1; background:#fdf2f4; overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:32px; flex-shrink:0;">
                        <img src="<?= BASE_URL ?>/image_file.php?type=product&id=<?= $hp['id'] ?>"
                             alt="<?= htmlspecialchars($hp['name']) ?>"
                             style="width:100%; height:100%; object-fit:cover;"
                             onerror="this.style.display='none';this.parentElement.innerHTML='💄';">
                    </div>
                    <div style="padding:10px 12px; flex:1; display:flex; flex-direction:column; justify-content:flex-start;">
                        <div style="font-size:10px; color:#c09aaa; font-weight:600; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($hp['brand']) ?></div>
                        <div style="font-size:12px; color:#333; font-weight:700; line-height:1.4; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;"><?= htmlspecialchars($hp['name']) ?></div>
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
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; align-items:stretch;">
                <?php foreach ($coldProducts as $cp): ?>
                <a href="product.php?id=<?= $cp['id'] ?>" style="text-decoration:none; background:white; border-radius:16px; border:1.5px solid #d8e2ed; overflow:hidden; display:flex; flex-direction:column; height:100%; transition:box-shadow .2s, transform .2s;" onmouseover="this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <div style="width:100%; aspect-ratio:1; background:#edf3f9; overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:32px; flex-shrink:0;">
                        <img src="<?= BASE_URL ?>/image_file.php?type=product&id=<?= $cp['id'] ?>"
                             alt="<?= htmlspecialchars($cp['name']) ?>"
                             style="width:100%; height:100%; object-fit:cover;"
                             onerror="this.style.display='none';this.parentElement.innerHTML='💄';">
                    </div>
                    <div style="padding:10px 12px; flex:1; display:flex; flex-direction:column; justify-content:flex-start;">
                        <div style="font-size:10px; color:#8a9bbf; font-weight:600; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($cp['brand']) ?></div>
                        <div style="font-size:12px; color:#333; font-weight:700; line-height:1.4; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;"><?= htmlspecialchars($cp['name']) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($mildProducts)): ?>
    <?php
    $skinTypeLabel = $profile['skin_type'] ?? '';
    $makeupFinishLabel = $profile['makeup_finish'] ?? '';
    $subtitleParts = [];
    if ($skinTypeLabel) $subtitleParts[] = $skinTypeLabel;
    if ($makeupFinishLabel) $subtitleParts[] = $makeupFinishLabel . '妝感';
    $dryMatteMismatch = in_array($skinTypeLabel, ['乾性皮', '混乾皮']) && $makeupFinishLabel === '霧面';
    if ($dryMatteMismatch) {
        $recommendSubtitle = '乾性皮建議優先補水，為你挑選保濕型底妝（霧面偏好已調整為保濕優先）';
    } elseif (!empty($subtitleParts)) {
        $recommendSubtitle = '根據你的' . implode('、', $subtitleParts) . '，為你挑選適合的輕薄日常底妝';
    } else {
        $recommendSubtitle = '根據你的膚質，為你挑選適合的輕薄日常底妝';
    }
    ?>
    <!-- 日常產品推薦（永遠顯示） -->
    <div style="margin-bottom:28px;">
        <div style="font-size:15px; font-weight:700; color:var(--text); margin-bottom:4px;">產品推薦</div>
        <div style="font-size:13px; color:var(--text-3); margin-bottom:14px;"><?= htmlspecialchars($recommendSubtitle) ?></div>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; align-items:stretch;">
            <?php foreach ($mildProducts as $mp): ?>
            <a href="product.php?id=<?= $mp['id'] ?>" style="text-decoration:none; background:var(--card); border-radius:16px; border:1.5px solid var(--border); overflow:hidden; display:flex; flex-direction:column; height:100%; transition:box-shadow .2s, transform .2s;" onmouseover="this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                <div style="width:100%; aspect-ratio:1; background:var(--bg); overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:32px; flex-shrink:0;">
                    <img src="<?= BASE_URL ?>/image_file.php?type=product&id=<?= $mp['id'] ?>"
                         alt="<?= htmlspecialchars($mp['name']) ?>"
                         style="width:100%; height:100%; object-fit:cover;"
                         onerror="this.style.display='none';this.parentElement.innerHTML='💄';">
                </div>
                <div style="padding:10px 12px; flex:1; display:flex; flex-direction:column; justify-content:flex-start;">
                    <div style="font-size:10px; color:var(--text-3); font-weight:600; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($mp['brand']) ?></div>
                    <div style="font-size:12px; color:var(--text); font-weight:700; line-height:1.4; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;"><?= htmlspecialchars($mp['name']) ?></div>
                    <div id="shade-<?= $mp['id'] ?>" style="display:<?= !empty($mp['shade']) ? 'flex' : 'none' ?>;align-items:center;gap:5px;margin-top:6px;">
                        <span id="shade-dot-<?= $mp['id'] ?>" style="width:14px;height:14px;border-radius:50%;background:<?= htmlspecialchars($mp['shade']['color_hex'] ?? '') ?>;border:1px solid rgba(0,0,0,.12);flex-shrink:0;"></span>
                        <span id="shade-name-<?= $mp['id'] ?>" style="font-size:10px;color:var(--text-3);">推薦色號 <?= htmlspecialchars($mp['shade']['color_name'] ?? '') ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


<?php endif; ?>
</div>
</main>

<!-- 回報 Modal (AI推薦頁用) -->
<div id="skinReportModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;padding:28px;width:min(440px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.18);position:relative;">
    <button onclick="closeSkinReport()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:#aaa;">✕</button>
    <h3 style="margin:0 0 4px;font-size:17px;font-weight:700;">回報產品狀況</h3>
    <p id="skinReportName" style="font-size:13px;color:#999;margin:0 0 18px;"></p>
    <input type="hidden" id="skinReportProductId">
    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">回報類型 <span style="color:#e74c3c">*</span></label>
    <select id="skinReportType" style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:inherit;outline:none;margin-bottom:14px;">
      <option value="">請選擇回報類型</option>
      <option value="ai_not_suitable">AI 推薦不適合我的膚質</option>
      <option value="discontinued">產品已停產</option>
      <option value="new_version">已出新版本</option>
      <option value="wrong_info">資訊有誤</option>
      <option value="other">其他</option>
    </select>
    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">補充說明（選填）</label>
    <textarea id="skinReportDesc" rows="3" placeholder="請說明不適合的原因，例如：太油膩、會過敏、遮瑕力不夠等"
      style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-family:inherit;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
    <div style="display:flex;gap:10px;margin-top:18px;">
      <button onclick="closeSkinReport()" style="flex:1;padding:11px;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;font-size:14px;font-weight:600;cursor:pointer;color:#555;">取消</button>
      <button onclick="submitSkinReport()" style="flex:1;padding:11px;border-radius:10px;border:none;background:#c26b7c;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">送出回報</button>
    </div>
    <p id="skinReportMsg" style="margin-top:10px;font-size:13px;text-align:center;min-height:18px;"></p>
  </div>
</div>

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

    // 初始執行
    if (select.value === 'auto') {
        runAuto();
    } else {
        const [lat, lon] = select.value.split(',');
        checkWeather(parseFloat(lat), parseFloat(lon));
    }
})();

function openSkinReport(id, name) {
  document.getElementById('skinReportProductId').value = id;
  document.getElementById('skinReportName').textContent = '產品：' + name;
  document.getElementById('skinReportType').value = '';
  document.getElementById('skinReportDesc').value = '';
  document.getElementById('skinReportMsg').textContent = '';
  const m = document.getElementById('skinReportModal');
  m.style.display = 'flex';
}
function closeSkinReport() {
  document.getElementById('skinReportModal').style.display = 'none';
}
document.getElementById('skinReportModal').addEventListener('click', function(e) {
  if (e.target === this) closeSkinReport();
});
// ── 色卡 & 妝容快速編輯 ──────────────────────────────────────────
const BASE = '<?= BASE_URL ?>';
const MILD_PRODUCT_IDS = [<?= implode(',', array_column($mildProducts ?? [], 'id')) ?>];
let _swatchLoaded = false;

function openSwatchModal() {
    const modal = document.getElementById('swatchModal');
    modal.style.display = 'flex';
    if (_swatchLoaded) return;
    _swatchLoaded = true;
    fetch(BASE + '/AI/getSkinTones.php')
        .then(r => r.json())
        .then(tones => {
            if (!Array.isArray(tones)) return;
            const groups = {};
            tones.forEach(t => {
                const cat = t.category || '其他';
                if (!groups[cat]) groups[cat] = [];
                groups[cat].push(t);
            });
            const container = document.getElementById('swatchGroups');
            container.innerHTML = '';
            Object.entries(groups).forEach(([cat, items]) => {
                const wrap = document.createElement('div');
                wrap.innerHTML = `<div style="font-size:12px;font-weight:700;color:#9b7b84;margin-bottom:8px;">${cat}</div>`;
                const row = document.createElement('div');
                row.style.cssText = 'display:flex;flex-wrap:wrap;gap:10px;';
                items.forEach(t => {
                    const btn = document.createElement('button');
                    btn.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:4px;background:none;border:none;cursor:pointer;padding:4px;';
                    const isCurrent = (t.toneName === '<?= addslashes($skinTone ?? '') ?>');
                    btn.innerHTML = `
                        <span style="width:40px;height:40px;border-radius:50%;display:block;background:${t.hex || '#ccc'};
                            border:${isCurrent ? '3px solid #6b2d3e;box-shadow:0 0 0 2px #fff,0 0 0 4px #6b2d3e' : '2px solid rgba(0,0,0,.1)'};"></span>
                        <span style="font-size:10px;color:#9b7b84;text-align:center;max-width:48px;line-height:1.2;">${t.toneName}</span>`;
                    btn.onclick = () => selectSkinTone(t.toneName, t.hex || '', btn);
                    row.appendChild(btn);
                });
                wrap.appendChild(row);
                container.appendChild(wrap);
            });
        })
        .catch(() => {
            document.getElementById('swatchGroups').innerHTML = '<p style="color:#e74c3c;font-size:13px;">載入失敗，請稍後再試</p>';
        });
}
function closeSwatchModal() {
    document.getElementById('swatchModal').style.display = 'none';
}
document.getElementById('swatchModal').addEventListener('click', function(e) {
    if (e.target === this) closeSwatchModal();
});

async function selectSkinTone(toneName, hex, btnEl) {
    try {
        const resp = await fetch('updatePreferences.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ skin_tone: toneName })
        });
        const result = await resp.json();
        if (!result.ok) return;

        closeSwatchModal();

        // 更新膚色 tag
        const dot = document.getElementById('profileToneDot');
        const label = document.getElementById('profileToneLabel');
        if (dot) dot.style.background = hex;
        if (label) label.textContent = toneName;

        // 更新產品卡色號
        if (MILD_PRODUCT_IDS.length === 0) return;
        const shadesResp = await fetch(
            'getShadesByTone.php?skin_tone=' + encodeURIComponent(toneName) +
            '&pids=' + MILD_PRODUCT_IDS.join(',')
        );
        const shadesData = await shadesResp.json();
        if (!shadesData.shades) return;

        MILD_PRODUCT_IDS.forEach(pid => {
            const shade = shadesData.shades[pid];
            const wrap  = document.getElementById('shade-' + pid);
            if (!wrap) return;
            if (!shade) { wrap.style.display = 'none'; return; }
            document.getElementById('shade-dot-'  + pid).style.background = shade.color_hex;
            document.getElementById('shade-name-' + pid).textContent = '推薦色號 ' + shade.color_name;
            wrap.style.display = 'flex';
        });
    } catch(e) {}
}

function selectMakeupOption(type, value) {
    const attr = type === 'finish' ? 'data-finish' : 'data-style';
    document.querySelectorAll(`[${attr}]`).forEach(btn => {
        btn.classList.toggle('makeup-pill--active', btn.getAttribute(attr) === value);
    });
}

function toggleMakeupEdit() {
    const panel = document.getElementById('makeupEditPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

async function saveMakeupPrefs() {
    const finishBtn = document.querySelector('.makeup-pill--active[data-finish]');
    const styleBtn  = document.querySelector('.makeup-pill--active[data-style]');
    const finish = finishBtn ? finishBtn.getAttribute('data-finish') : '';
    const style  = styleBtn  ? styleBtn.getAttribute('data-style')  : '';
    try {
        const resp = await fetch('updatePreferences.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ makeup_finish: finish, makeup_style: style })
        });
        const result = await resp.json();
        if (result.ok) {
            document.getElementById('makeupEditPanel').style.display = 'none';
            const finishTag = document.getElementById('profileFinishLabel');
            const styleTag  = document.getElementById('profileStyleLabel');
            if (finishTag) finishTag.textContent = finish;
            if (styleTag)  styleTag.textContent  = style;
        }
    } catch(e) {}
}

async function submitSkinReport() {
  const type = document.getElementById('skinReportType').value;
  const msg  = document.getElementById('skinReportMsg');
  if (!type) { msg.style.color = '#e74c3c'; msg.textContent = '請選擇回報類型'; return; }
  const payload = {
    product_id:   document.getElementById('skinReportProductId').value,
    report_type:  type,
    description:  document.getElementById('skinReportDesc').value.trim()
  };
  try {
    const resp   = await fetch('report_product.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const result = await resp.json();
    msg.style.color = result.success ? '#16a34a' : '#e74c3c';
    msg.textContent = result.message;
    if (result.success) setTimeout(closeSkinReport, 1800);
  } catch (e) { msg.style.color = '#e74c3c'; msg.textContent = '網路錯誤，請稍後再試'; }
}
</script>
</body>
</html>
