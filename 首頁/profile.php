<?php
session_start();

// 檢查是否已登入
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// 檢查是否需要建立個人資料表
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            gender VARCHAR(10),
            skin_type VARCHAR(50),
            skin_tone VARCHAR(50),
            skin_concerns TEXT,
            age INT,
            allergies TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    die('資料庫錯誤：' . $e->getMessage());
}

$message = '';
$messageType = '';

// 檢查是否為新用戶
$isNewUser = isset($_GET['new']);

// 獲取現有個人資料
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE username = ?");
$stmt->execute([$_SESSION['user']]);
$profile = $stmt->fetch();

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

// 膚質選項
$skinTypes = [
    '乾燥肌' => '乾燥肌',
    '油性肌' => '油性肌',
    '混合肌' => '混合肌',
    '中性肌' => '中性肌',
    '敏感肌' => '敏感肌'
];

// 膚色選項
$skinTones = [
    '非常淺' => '非常淺',
    '淺色' => '淺色',
    '中等淺' => '中等淺',
    '中等' => '中等',
    '中等深' => '中等深',
    '深色' => '深色',
    '非常深' => '非常深'
];

// 肌膚問題選項
$skinConcernsList = [
    '痘痘' => '痘痘',
    '粉刺' => '粉刺',
    '毛孔粗大' => '毛孔粗大',
    '黑斑' => '黑斑',
    '細紋' => '細紋',
    '皺紋' => '皺紋',
    '乾燥脫皮' => '乾燥脫皮',
    '油光滿面' => '油光滿面',
    '暗瘡疤痕' => '暗瘡疤痕',
    '曬斑' => '曬斑',
    '黑眼圈' => '黑眼圈',
    '浮腫' => '浮腫'
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人資料</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
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
                        window.location.href = 'index.php';
                    }, 1500);
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <div class="profile-header">
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
                                <option value="<?php echo $key; ?>" <?php echo ($profile['skin_type'] ?? '') === $key ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $key; ?>" <?php echo ($profile['skin_tone'] ?? '') === $key ? 'selected' : ''; ?>>
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
    </div>
</main>

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
function openAITest() {
    document.getElementById('aiTestModal').classList.add('active');
}

function closeAITest() {
    document.getElementById('aiTestModal').classList.remove('active');
}

function startAITest() {
    // 重定向到AI測試頁面
    window.location.href = '../AI/story1.php?returnTo=profile';
}

// 點擊模態框外部關閉
document.getElementById('aiTestModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAITest();
    }
});
</script>

<?php include 'footer.php'; ?>

</body>
</html>