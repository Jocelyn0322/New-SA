<?php
session_start();

// 檢查是否已登入
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$message = '';
$messageType = '';

// 確保 avatar_url 欄位存在
$pdo->exec("ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS avatar_url TEXT");

// 檢查是否為新用戶
$isNewUser = isset($_GET['new']);

// 獲取現有個人資料
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE username = ?");
$stmt->execute([$_SESSION['user']]);
$profile = $stmt->fetch();

// 取得帳號 email
$stmtU = $pdo->prepare("SELECT email FROM users WHERE username = ?");
$stmtU->execute([$_SESSION['user']]);
$currentEmail = $stmtU->fetchColumn() ?: '';

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $gender = $_POST['gender'] ?? '';
    $skinType = $_POST['skin_type'] ?? '';
    $skinTone = $_POST['skin_tone'] ?? '';
    $skinConcerns = implode(', ', $_POST['skin_concerns'] ?? []);
    $age = $_POST['age'] ?? null;
    $allergies = $_POST['allergies'] ?? '';

    if ($profile) {
        // 更新現有資料
        $stmt = $pdo->prepare("
            UPDATE user_profiles 
            SET gender = ?, skin_type = ?, skin_tone = ?, skin_concerns = ?, age = ?, allergies = ?
            WHERE username = ?
        ");
        $stmt->execute([$gender, $skinType, $skinTone, $skinConcerns, $age, $allergies, $_SESSION['user']]);
    } else {
        // 插入新資料
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (username, gender, skin_type, skin_tone, skin_concerns, age, allergies)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user'], $gender, $skinType, $skinTone, $skinConcerns, $age, $allergies]);
    }

    $message = '個人資料儲存成功！';
    $messageType = 'success';

    // 重新獲取資料
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE username = ?");
    $stmt->execute([$_SESSION['user']]);
    $profile = $stmt->fetch();
}

// 舊標籤 → 新標籤對應（相容歷史資料）
$skinTypeLegacyMap = [
    '乾燥肌' => '乾性皮',
    '油性肌' => '油性皮',
    '混合肌' => '混油皮',
    '混合偏乾' => '混乾皮',
    '中性肌' => '中性皮',
    '敏感性肌' => '敏感肌',
];
$profileSkinType = $profile['skin_type'] ?? '';
if (isset($skinTypeLegacyMap[$profileSkinType])) {
    $profileSkinType = $skinTypeLegacyMap[$profileSkinType];
}

// 膚質選項
$skinTypes = [
    '乾性皮' => '乾性皮',
    '混乾皮' => '混乾皮',
    '中性皮' => '中性皮',
    '混油皮' => '混油皮',
    '油性皮' => '油性皮',
    '敏感肌' => '敏感肌',
];

// 舊膚色標籤對應
$skinToneLegacyMap = [
    '中等淺' => '中一白',
    '偏白'   => '中二白',
    '偏深'   => '黃三白',
    '中等'   => '黃一白',
];
$profileSkinTone = $profile['skin_tone'] ?? '';
if (isset($skinToneLegacyMap[$profileSkinTone])) {
    $profileSkinTone = $skinToneLegacyMap[$profileSkinTone];
}

// 膚色選項（來自資料庫）
$skinTones = [
    '中一白'   => '中一白',
    '中二白'   => '中二白',
    '中三白'   => '中三白',
    '中性冷一白' => '中性冷一白',
    '中性冷二白' => '中性冷二白',
    '中性暖一白' => '中性暖一白',
    '中性暖二白' => '中性暖二白',
    '偏紅冷一白' => '偏紅冷一白',
    '偏紅冷二白' => '偏紅冷二白',
    '偏紅暖一白' => '偏紅暖一白',
    '偏紅暖二白' => '偏紅暖二白',
    '偏綠冷一白' => '偏綠冷一白',
    '偏綠冷二白' => '偏綠冷二白',
    '偏綠暖一白' => '偏綠暖一白',
    '偏綠暖二白' => '偏綠暖二白',
    '橄欖一白'  => '橄欖一白',
    '橄欖二白'  => '橄欖二白',
    '橄欖三白'  => '橄欖三白',
    '粉一白'   => '粉一白',
    '粉二白'   => '粉二白',
    '粉三白'   => '粉三白',
    '黃一白'   => '黃一白',
    '黃二白'   => '黃二白',
    '黃三白'   => '黃三白',
];

// 肌膚問題選項
$skinConcernsList = [
    '敏感肌' => '敏感肌',
    '痘痘'   => '痘痘',
    '粉刺'   => '粉刺',
    '毛孔粗大' => '毛孔粗大',
    '黑斑'   => '黑斑',
    '細紋'   => '細紋',
    '皺紋'   => '皺紋',
    '乾燥脫皮' => '乾燥脫皮',
    '油光滿面' => '油光滿面',
    '暗瘡疤痕' => '暗瘡疤痕',
    '曬斑'   => '曬斑',
    '黑眼圈' => '黑眼圈',
    '浮腫'   => '浮腫',
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人資料</title>
    <link rel="stylesheet" href="style.css?v=2">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'LXGW WenKai TC', '標楷體', 'BiauKai', 'DFKai-SB', serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .profile-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .profile-header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }

        .profile-header p {
            color: #666;
            font-size: 16px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ff5a7e;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff5a7e;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .checkbox-item:hover {
            background: #f0f0f0;
        }

        .checkbox-item input {
            margin-right: 8px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .radio-item {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            background: #f9f9f9;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .radio-item:hover {
            background: #f0f0f0;
        }

        .radio-item input {
            margin-right: 8px;
        }

        .save-btn {
            background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
            margin-top: 20px;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 90, 126, 0.3);
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .welcome-banner {
            background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }

        .welcome-banner h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            opacity: 0.9;
        }

        .ai-test-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .ai-test-modal.active {
            display: flex;
        }

        .ai-test-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .ai-test-modal-content h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: #333;
        }

        .ai-test-modal-content p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .modal-btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn-group button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .modal-btn-primary {
            background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%);
            color: white;
        }

        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 90, 126, 0.3);
        }

        .modal-btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .modal-btn-secondary:hover {
            background: #e0e0e0;
        }

        /* ── Avatar ── */
        .avatar-wrapper {
            position: relative;
            width: 96px;
            height: 96px;
            margin: 0 auto 14px;
            cursor: pointer;
        }
        .avatar-circle {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, #ff5a7e, #ff3a6f);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #fff;
            font-weight: 700;
            border: 3px solid #fff;
            box-shadow: 0 4px 16px rgba(255,90,126,0.3);
        }
        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-edit-icon {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 28px;
            height: 28px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            border: 2px solid #ff5a7e;
        }
        .avatar-hint {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 10px;
        }

        /* ── Account settings ── */
        .account-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid #f0f0f0;
            gap: 12px;
        }
        .account-row:last-child { border-bottom: none; }
        .account-label {
            font-size: 13px;
            color: #888;
            margin-bottom: 2px;
        }
        .account-value {
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }
        .edit-btn {
            background: #f5f5f5;
            color: #555;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .edit-btn:hover { background: #e8e8e8; }

        /* ── Modal overlay ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 32px 28px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .modal-box h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #222;
        }
        .modal-input {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 12px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .modal-input:focus { outline: none; border-color: #ff5a7e; }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 6px;
        }
        .modal-save-btn {
            flex: 1;
            background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .modal-save-btn:hover { opacity: 0.88; }
        .modal-cancel-btn {
            flex: 1;
            background: #f0f0f0;
            color: #555;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }
        .modal-cancel-btn:hover { background: #e4e4e4; }
        .modal-msg {
            font-size: 13px;
            margin-top: 8px;
            min-height: 20px;
            text-align: center;
        }
        .modal-msg.ok  { color: #28a745; }
        .modal-msg.err { color: #dc3545; }

        /* ── Danger zone ── */
        .danger-zone {
            margin-top: 40px;
            padding: 24px;
            border: 2px solid #f5c6cb;
            border-radius: 12px;
            background: #fff5f6;
        }
        .danger-zone h2 {
            font-size: 18px;
            color: #c82333;
            margin-bottom: 8px;
        }
        .danger-zone p {
            font-size: 13px;
            color: #888;
            margin-bottom: 16px;
        }
        .delete-account-btn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 11px 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .delete-account-btn:hover { background: #b02a37; }

        /* ── Delete reasons ── */
        .reason-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        .reason-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            background: #f9f9f9;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        .reason-item input { cursor: pointer; }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                grid-template-columns: repeat(2, 1fr);
            }

            .profile-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="profile-page">
    <?php if ($isNewUser): ?>
        <div class="welcome-banner">
            <h2>🎉 歡迎加入！</h2>
            <p>請填寫您的個人資料，讓我們為您提供更個人化的護膚建議</p>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
            <?php if ($messageType === 'success' && $isNewUser): ?>
                <script>
                    setTimeout(function() {
                        window.location.href = '/SA/New-SA/產品/index.php';
                    }, 1500);
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <div class="profile-header">
            <!-- 頭貼 -->
            <div class="avatar-wrapper" onclick="document.getElementById('avatarInput').click()" title="點擊更換頭貼">
                <div class="avatar-circle" id="avatarCircle">
                    <?php if (!empty($profile['avatar_url'])): ?>
                        <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" id="avatarImg">
                    <?php else: ?>
                        <span id="avatarInitial"><?php echo strtoupper(mb_substr($_SESSION['user'], 0, 1)); ?></span>
                        <img src="" id="avatarImg" style="display:none;">
                    <?php endif; ?>
                </div>
                <div class="avatar-edit-icon">📷</div>
            </div>
            <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="uploadAvatar(this)">
            <p class="avatar-hint" id="avatarStatus">點擊頭像可更換圖片（最大 5MB）</p>
            <h1>👤 個人資料</h1>
            <p>填寫您的肌膚資訊，獲得更精準的護膚建議</p>
        </div>

        <form method="post">
            <div class="form-section">
                <h2>基本資料</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="gender">性別</label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="gender" value="女性" <?php echo ($profile['gender'] ?? '') === '女性' ? 'checked' : ''; ?>>
                                女性
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="gender" value="男性" <?php echo ($profile['gender'] ?? '') === '男性' ? 'checked' : ''; ?>>
                                男性
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="gender" value="其他" <?php echo ($profile['gender'] ?? '') === '其他' ? 'checked' : ''; ?>>
                                其他
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="age">年齡</label>
                        <input type="number" id="age" name="age" min="10" max="100" placeholder="請輸入年齡" value="<?php echo htmlspecialchars($profile['age'] ?? ''); ?>">
                    </div>
                </div>
            </div>

        </form>

        <!-- ── 帳號設定 ── -->
        <div class="form-section" style="margin-top:30px;">
            <h2>帳號設定</h2>
            <div class="account-row">
                <div>
                    <div class="account-label">帳號名稱</div>
                    <div class="account-value" id="displayUsername"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <button type="button" class="edit-btn" onclick="openInfoEdit('username')">修改</button>
            </div>
            <div class="account-row">
                <div>
                    <div class="account-label">Email</div>
                    <div class="account-value" id="displayEmail"><?php echo htmlspecialchars($currentEmail); ?></div>
                </div>
                <button type="button" class="edit-btn" onclick="openInfoEdit('email')">修改</button>
            </div>
            <div class="account-row">
                <div>
                    <div class="account-label">密碼</div>
                    <div class="account-value">••••••••</div>
                </div>
                <button type="button" class="edit-btn" onclick="openPwdEdit()">修改</button>
            </div>
        </div>

        <form method="post">
            <div class="form-section">
                <h2>肌膚資料</h2>
                
                <div style="background: #f0f7ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2563eb;">
                    <p style="margin: 0 0 10px 0; color: #1e40af; font-weight: 600; font-size: 14px;">💡 不知道自己的膚質或膚色？</p>
                    <p style="margin: 0 0 12px 0; color: #1e40af; font-size: 13px;">使用我們的 AI 智慧檢測工具，拍張臉部照片或回答幾個問題，立即為您精準分析。</p>
                    <a href="javascript:void(0)" onclick="openAITest()" style="display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s;">
                        🤖 進行 AI 膚色膚質檢測
                    </a>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="skin_type">膚質</label>
                        <select id="skin_type" name="skin_type">
                            <option value="">請選擇膚質</option>
                            <?php foreach ($skinTypes as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $profileSkinType === $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="skin_tone">膚色</label>
                        <select id="skin_tone" name="skin_tone">
                            <option value="">請選擇膚色</option>
                            <?php foreach ($skinTones as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $profileSkinTone === $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>肌膚困擾（可複選）</label>
                    <div class="checkbox-group">
                        <?php foreach ($skinConcernsList as $key => $value): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="skin_concerns[]" value="<?php echo $key; ?>" 
                                    <?php echo isset($profile['skin_concerns']) && in_array($key, explode(',', $profile['skin_concerns'])) ? 'checked' : ''; ?>>
                                <?php echo $value; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="allergies">過敏史或敏感成分</label>
                    <textarea id="allergies" name="allergies" placeholder="請列出您過敏的成分或產品（如：酒精、香精、某特定成分）"><?php echo htmlspecialchars($profile['allergies'] ?? ''); ?></textarea>
                </div>
            </div>

            <button type="submit" name="save_profile" class="save-btn">💾 儲存個人資料</button>
        </form>

        <!-- ── 危險區域 ── -->
        <div class="danger-zone">
            <h2>危險區域</h2>
            <p>刪除帳號後，您的所有資料（個人資料、影片、留言、評分）將被永久刪除，且無法復原。</p>
            <button class="delete-account-btn" onclick="openDeleteModal()">刪除帳號</button>
        </div>
    </div>
</main>

<!-- ── 修改帳號資訊 Modal ── -->
<div id="infoEditModal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="infoEditTitle">修改帳號名稱</h3>
        <input class="modal-input" id="infoEditField" type="text" placeholder="">
        <input class="modal-input" id="infoEditPwd" type="password" placeholder="請輸入目前密碼">
        <div class="modal-actions">
            <button class="modal-save-btn" onclick="saveInfo()">儲存</button>
            <button class="modal-cancel-btn" onclick="closeInfoEdit()">取消</button>
        </div>
        <div class="modal-msg" id="infoEditMsg"></div>
    </div>
</div>

<!-- ── 修改密碼 Modal ── -->
<div id="pwdEditModal" class="modal-overlay">
    <div class="modal-box">
        <h3>修改密碼</h3>
        <input class="modal-input" id="pwdCurrent" type="password" placeholder="目前密碼">
        <input class="modal-input" id="pwdNew" type="password" placeholder="新密碼（至少 6 字元）">
        <input class="modal-input" id="pwdConfirm" type="password" placeholder="確認新密碼">
        <div class="modal-actions">
            <button class="modal-save-btn" onclick="savePwd()">儲存</button>
            <button class="modal-cancel-btn" onclick="closePwdEdit()">取消</button>
        </div>
        <div class="modal-msg" id="pwdEditMsg"></div>
    </div>
</div>

<!-- ── 刪除帳號 Modal ── -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <h3>⚠️ 刪除帳號</h3>
        <p style="font-size:14px;color:#555;margin-bottom:14px;">請勾選您刪除帳號的原因（可複選）：</p>
        <div class="reason-list">
            <?php foreach ([
                '不再使用此平台',
                '找到更好的替代品',
                '隱私或安全疑慮',
                '功能不符合需求',
                '帳號遭到盜用',
                '其他原因',
            ] as $r): ?>
            <label class="reason-item">
                <input type="checkbox" name="delete_reason" value="<?php echo htmlspecialchars($r); ?>">
                <?php echo htmlspecialchars($r); ?>
            </label>
            <?php endforeach; ?>
        </div>
        <input class="modal-input" id="deletePwd" type="password" placeholder="請輸入密碼確認">
        <div class="modal-actions">
            <button class="modal-save-btn" style="background:linear-gradient(135deg,#dc3545,#b02a37);" onclick="confirmDelete()">確認刪除帳號</button>
            <button class="modal-cancel-btn" onclick="closeDeleteModal()">取消</button>
        </div>
        <div class="modal-msg" id="deleteMsg"></div>
    </div>
</div>

<!-- AI 測試模態框 -->
<div id="aiTestModal" class="ai-test-modal">
    <div class="ai-test-modal-content">
        <h3>🤖 AI 智慧檢測</h3>
        <p>我們將使用人工智慧幫您分析膚色和膚質。您可以選擇：</p>
        <div class="modal-btn-group">
            <button class="modal-btn-secondary" onclick="closeAITest()">取消</button>
            <button class="modal-btn-primary" onclick="startAITest()">開始檢測</button>
        </div>
    </div>
</div>

<script>
// ── AI Test Modal ──────────────────────────────────────────────────
function openAITest() { document.getElementById('aiTestModal').classList.add('active'); }
function closeAITest() { document.getElementById('aiTestModal').classList.remove('active'); }
function startAITest() { window.location.href = '../AI/story1.php?returnTo=profile'; }
document.getElementById('aiTestModal').addEventListener('click', function(e) { if (e.target === this) closeAITest(); });

// ── Avatar upload ──────────────────────────────────────────────────
async function uploadAvatar(input) {
    if (!input.files[0]) return;
    const status = document.getElementById('avatarStatus');
    status.textContent = '上傳中...';
    const form = new FormData();
    form.append('avatar', input.files[0]);
    try {
        const res  = await fetch('upload_avatar.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            const img = document.getElementById('avatarImg');
            const init = document.getElementById('avatarInitial');
            img.src = data.url;
            img.style.display = '';
            if (init) init.style.display = 'none';
            status.textContent = '頭貼已更新！';
        } else {
            status.textContent = data.message || '上傳失敗';
        }
    } catch(e) {
        status.textContent = '網路錯誤，請稍後再試';
    }
    input.value = '';
}

// ── Info edit (username / email) ───────────────────────────────────
var _infoEditMode = 'username';

function openInfoEdit(mode) {
    _infoEditMode = mode;
    const modal = document.getElementById('infoEditModal');
    const title = document.getElementById('infoEditTitle');
    const field = document.getElementById('infoEditField');
    document.getElementById('infoEditMsg').textContent = '';
    document.getElementById('infoEditPwd').value = '';
    if (mode === 'username') {
        title.textContent = '修改帳號名稱';
        field.type = 'text';
        field.placeholder = '新帳號名稱';
        field.value = document.getElementById('displayUsername').textContent;
    } else {
        title.textContent = '修改 Email';
        field.type = 'email';
        field.placeholder = '新 Email';
        field.value = document.getElementById('displayEmail').textContent;
    }
    modal.classList.add('active');
}

function closeInfoEdit() { document.getElementById('infoEditModal').classList.remove('active'); }

async function saveInfo() {
    const field   = document.getElementById('infoEditField').value.trim();
    const pwd     = document.getElementById('infoEditPwd').value;
    const msg     = document.getElementById('infoEditMsg');
    msg.className = 'modal-msg';
    msg.textContent = '';

    if (!field || !pwd) { msg.className = 'modal-msg err'; msg.textContent = '請填寫所有欄位'; return; }

    const body = { action: 'update_info', current_password: pwd };
    if (_infoEditMode === 'username') body.username = field;
    else body.email = field;

    try {
        const res  = await fetch('update_account.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const data = await res.json();
        if (data.success) {
            msg.className = 'modal-msg ok';
            msg.textContent = data.message;
            if (_infoEditMode === 'username' && data.new_username) {
                document.getElementById('displayUsername').textContent = data.new_username;
            } else if (_infoEditMode === 'email') {
                document.getElementById('displayEmail').textContent = field;
            }
            setTimeout(closeInfoEdit, 1200);
        } else {
            msg.className = 'modal-msg err';
            msg.textContent = data.message;
        }
    } catch(e) { msg.className = 'modal-msg err'; msg.textContent = '網路錯誤'; }
}

// ── Password edit ──────────────────────────────────────────────────
function openPwdEdit() {
    ['pwdCurrent','pwdNew','pwdConfirm'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('pwdEditMsg').textContent = '';
    document.getElementById('pwdEditModal').classList.add('active');
}
function closePwdEdit() { document.getElementById('pwdEditModal').classList.remove('active'); }

async function savePwd() {
    const current = document.getElementById('pwdCurrent').value;
    const newPwd  = document.getElementById('pwdNew').value;
    const confirm = document.getElementById('pwdConfirm').value;
    const msg     = document.getElementById('pwdEditMsg');
    msg.className = 'modal-msg';
    msg.textContent = '';

    try {
        const res  = await fetch('update_account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_password', current_password: current, new_password: newPwd, confirm_password: confirm })
        });
        const data = await res.json();
        if (data.success) {
            msg.className = 'modal-msg ok';
            msg.textContent = data.message;
            setTimeout(closePwdEdit, 1200);
        } else {
            msg.className = 'modal-msg err';
            msg.textContent = data.message;
        }
    } catch(e) { msg.className = 'modal-msg err'; msg.textContent = '網路錯誤'; }
}

// ── Delete account ─────────────────────────────────────────────────
function openDeleteModal() {
    document.querySelectorAll('#deleteModal input[type=checkbox]').forEach(c => c.checked = false);
    document.getElementById('deletePwd').value = '';
    document.getElementById('deleteMsg').textContent = '';
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }

async function confirmDelete() {
    const reasons = Array.from(document.querySelectorAll('#deleteModal input[type=checkbox]:checked')).map(c => c.value);
    const pwd     = document.getElementById('deletePwd').value;
    const msg     = document.getElementById('deleteMsg');
    msg.className = 'modal-msg';

    if (reasons.length === 0) { msg.className = 'modal-msg err'; msg.textContent = '請至少勾選一個原因'; return; }
    if (!pwd)                 { msg.className = 'modal-msg err'; msg.textContent = '請輸入密碼確認'; return; }

    try {
        const res  = await fetch('delete_account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: pwd, reasons: reasons })
        });
        const data = await res.json();
        if (data.success) {
            msg.className = 'modal-msg ok';
            msg.textContent = '帳號已刪除，即將跳轉...';
            setTimeout(() => { window.location.href = data.redirect; }, 1500);
        } else {
            msg.className = 'modal-msg err';
            msg.textContent = data.message;
        }
    } catch(e) { msg.className = 'modal-msg err'; msg.textContent = '網路錯誤'; }
}

// 點擊背景關閉 modals
['infoEditModal','pwdEditModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
});
</script>

<?php include 'footer.php'; ?>

</body>
</html>