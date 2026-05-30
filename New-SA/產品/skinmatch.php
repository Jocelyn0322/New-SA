<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../db.php';

$profile    = null;
$products   = [];
$hasProfile = false;
$toneHex    = '';

// ── AJAX: update makeup preference ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) { echo json_encode(['success'=>false]); exit; }
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $finish = trim($data['makeup_finish'] ?? '');
    $style  = trim($data['makeup_style']  ?? '');
    try {
        $pdo->prepare("INSERT INTO user_profiles (username, makeup_finish, makeup_style)
            VALUES (?,?,?) ON DUPLICATE KEY UPDATE makeup_finish=VALUES(makeup_finish),
            makeup_style=VALUES(makeup_style), updated_at=NOW()")
            ->execute([$_SESSION['user'], $finish, $style]);
        echo json_encode(['success'=>true]);
    } catch(Exception $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ── Load user profile ─────────────────────────────────────────────
if (isset($_SESSION['user'])) {
    try {
        $stmt = $pdo->prepare("SELECT u.username, u.email, u.role,
            up.gender, up.skin_type, up.skin_tone, up.skin_concerns, up.age, up.allergies,
            up.makeup_finish, up.makeup_style, up.avatar_url, up.weight_l, up.weight_a, up.weight_b
            FROM users u LEFT JOIN user_profiles up ON u.username = up.username
            WHERE u.username = ?");
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
            "SELECT d.*, GROUP_CONCAT(pc.color_name ORDER BY pc.color_id SEPARATOR ',') AS color_names,
                    GROUP_CONCAT(pc.color_hex  ORDER BY pc.color_id SEPARATOR ',') AS color_hexes
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

// ── 天氣推薦產品（輕薄日常，舒適溫度時顯示） ─────────────────────
$mildProducts = [];
if (isset($pdo)) {
    try {
        $mildKw = ['輕薄', '自然', '裸妝', '日常', '通透', '輕盈', '空氣感'];
        $conditions = array_map(fn($k) => "purpose LIKE :kw_p_$k OR name LIKE :kw_n_$k", array_keys($mildKw));
        $sql = "SELECT id, name, brand, purpose, image_url FROM data WHERE category = '底妝' AND (" . implode(' OR ', $conditions) . ") LIMIT 20";
        $st = $pdo->prepare($sql);
        foreach ($mildKw as $i => $kw) {
            $st->bindValue(":kw_p_$i", '%' . $kw . '%');
            $st->bindValue(":kw_n_$i", '%' . $kw . '%');
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        usort($rows, function($a, $b) use ($mildKw, $_userSkinKw) {
            $tA = $a['purpose'] . $a['name'];
            $tB = $b['purpose'] . $b['name'];
            $wA = array_sum(array_map(fn($k) => mb_strpos($tA, $k) !== false ? 2 : 0, $mildKw));
            $sA = array_sum(array_map(fn($k) => mb_strpos($tA, $k) !== false ? 1 : 0, $_userSkinKw));
            $wB = array_sum(array_map(fn($k) => mb_strpos($tB, $k) !== false ? 2 : 0, $mildKw));
            $sB = array_sum(array_map(fn($k) => mb_strpos($tB, $k) !== false ? 1 : 0, $_userSkinKw));
            return ($wB + $sB) <=> ($wA + $sA);
        });
        $mildProducts = array_slice($rows, 0, 4);
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
            <button type="button" onclick="openMakeupModal()"
                style="font-size:13px;color:rgba(255,255,255,.65);background:none;border:none;cursor:pointer;padding:0;text-decoration:underline;">
                ✏️ 更換妝感偏好
            </button>
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
    $recommendSubtitle = !empty($subtitleParts)
        ? '根據你的' . implode('、', $subtitleParts) . '，為你挑選適合的輕薄日常底妝'
        : '根據你的膚質，為你挑選適合的輕薄日常底妝';
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

<!-- 妝感偏好 Modal -->
<div id="makeupModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;padding:28px;width:min(440px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.18);position:relative;">
    <button onclick="closeMakeupModal()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:#aaa;">✕</button>
    <h3 style="margin:0 0 20px;font-size:17px;font-weight:700;">✏️ 更換妝感偏好</h3>

    <div style="margin-bottom:18px;">
      <div style="font-size:13px;font-weight:600;color:#555;margin-bottom:10px;">妝感</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;" id="finishOptions">
        <?php foreach (['霧面','水光感','自然光澤'] as $f): ?>
        <button type="button" onclick="selectMakeupOpt('finish','<?= $f ?>')"
          data-val="<?= $f ?>"
          class="makeup-opt <?= $makeupFinish === $f ? 'active' : '' ?>"
          style="padding:7px 16px;border-radius:20px;border:1.5px solid <?= $makeupFinish===$f?'#c26b7c':'#e2e8f0' ?>;background:<?= $makeupFinish===$f?'#fdf0f3':'#fff' ?>;color:<?= $makeupFinish===$f?'#c26b7c':'#555' ?>;font-size:13px;cursor:pointer;font-family:inherit;">
          <?= $f ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="margin-bottom:24px;">
      <div style="font-size:13px;font-weight:600;color:#555;margin-bottom:10px;">妝容風格</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;" id="styleOptions">
        <?php foreach (['日常通勤','韓系清透','歐美立體','約會精緻'] as $s): ?>
        <button type="button" onclick="selectMakeupOpt('style','<?= $s ?>')"
          data-val="<?= $s ?>"
          class="makeup-opt <?= $makeupStyle === $s ? 'active' : '' ?>"
          style="padding:7px 16px;border-radius:20px;border:1.5px solid <?= $makeupStyle===$s?'#c26b7c':'#e2e8f0' ?>;background:<?= $makeupStyle===$s?'#fdf0f3':'#fff' ?>;color:<?= $makeupStyle===$s?'#c26b7c':'#555' ?>;font-size:13px;cursor:pointer;font-family:inherit;">
          <?= $s ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <button onclick="saveMakeupPref()" style="width:100%;padding:12px;border-radius:12px;border:none;background:#c26b7c;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">儲存</button>
    <p id="makeupModalMsg" style="margin-top:10px;font-size:13px;text-align:center;min-height:18px;"></p>
  </div>
</div>

<script>
let _makeupFinish = '<?= addslashes($makeupFinish) ?>';
let _makeupStyle  = '<?= addslashes($makeupStyle) ?>';

function openMakeupModal()  { document.getElementById('makeupModal').style.display = 'flex'; }
function closeMakeupModal() { document.getElementById('makeupModal').style.display = 'none'; }

document.getElementById('makeupModal').addEventListener('click', function(e) {
  if (e.target === this) closeMakeupModal();
});

function selectMakeupOpt(type, val) {
  if (type === 'finish') _makeupFinish = val;
  else _makeupStyle = val;

  const groupId = type === 'finish' ? 'finishOptions' : 'styleOptions';
  document.querySelectorAll('#' + groupId + ' button').forEach(btn => {
    const active = btn.dataset.val === val;
    btn.style.border      = active ? '1.5px solid #c26b7c' : '1.5px solid #e2e8f0';
    btn.style.background  = active ? '#fdf0f3' : '#fff';
    btn.style.color       = active ? '#c26b7c' : '#555';
  });
}

async function saveMakeupPref() {
  const msg = document.getElementById('makeupModalMsg');
  try {
    const res  = await fetch('skinmatch.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ makeup_finish: _makeupFinish, makeup_style: _makeupStyle })
    });
    const data = await res.json();
    if (data.success) {
      msg.style.color = '#2d7a50';
      msg.textContent = '儲存成功！';
      // 更新頁面上的 tag 顯示
      document.querySelectorAll('.profile-tag').forEach(tag => {
        const label = tag.querySelector('.tag-label');
        if (label?.textContent === '妝感')     tag.lastChild.textContent = ' ' + _makeupFinish;
        if (label?.textContent === '妝容風格') tag.lastChild.textContent = ' ' + _makeupStyle;
      });
      setTimeout(closeMakeupModal, 1000);
    } else {
      msg.style.color = '#c26b7c'; msg.textContent = '儲存失敗，請稍後再試';
    }
  } catch(e) {
    msg.style.color = '#c26b7c'; msg.textContent = '網路錯誤';
  }
}
</script>

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
