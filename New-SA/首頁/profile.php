<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/../db.php';

$message = '';
$messageType = '';

$isNewUser = isset($_GET['new']);

$stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE username = ?");
$stmt->execute([$_SESSION['user']]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE username = ?");
$stmt->execute([$_SESSION['user']]);
$profileRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$profile = array_merge($userRow, $profileRow);

$currentEmail = $userRow['email'] ?? '';
$joinedAt     = $userRow['created_at'] ?? '';
$joinedFmt    = $joinedAt ? date('Y/m/d', strtotime($joinedAt)) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $gender       = $_POST['gender'] ?? '';
    $skinType     = $_POST['skin_type'] ?? '';
    $skinTone     = $_POST['skin_tone'] ?? '';
    $skinConcerns = implode(', ', $_POST['skin_concerns'] ?? []);
    $age          = isset($_POST['age']) && $_POST['age'] !== '' ? intval($_POST['age']) : null;
    $allergies    = $_POST['allergies'] ?? '';
    $makeupFinish = $_POST['makeup_finish'] ?? '';
    $makeupStyle  = $_POST['makeup_style'] ?? '';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (username, gender, skin_type, skin_tone, skin_concerns, age, allergies, makeup_finish, makeup_style, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
              gender=VALUES(gender), skin_type=VALUES(skin_type), skin_tone=VALUES(skin_tone),
              skin_concerns=VALUES(skin_concerns), age=VALUES(age), allergies=VALUES(allergies),
              makeup_finish=VALUES(makeup_finish), makeup_style=VALUES(makeup_style), updated_at=NOW()
        ");
        $stmt->execute([$_SESSION['user'],$gender,$skinType,$skinTone,$skinConcerns,$age,$allergies,$makeupFinish,$makeupStyle]);
        $message = '個人資料儲存成功！';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = '儲存失敗：' . $e->getMessage();
        $messageType = 'error';
    }

    if (!empty($_POST['_ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $messageType === 'success', 'message' => $message]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['user']]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE username = ?");
    $stmt->execute([$_SESSION['user']]);
    $profileRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $profile = array_merge($userRow, $profileRow);
}

/* ── Stats ── */
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE user_id = ?");
    $s->execute([$_SESSION['user']]); $statLikes = (int)$s->fetchColumn();
} catch(Exception $e) { $statLikes = 0; }
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE uploaded_by = ? AND is_active = 1");
    $s->execute([$_SESSION['user']]); $statVideos = (int)$s->fetchColumn();
} catch(Exception $e) { $statVideos = 0; }
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM product_ratings WHERE username = ?");
    $s->execute([$_SESSION['user']]); $statRatings = (int)$s->fetchColumn();
} catch(Exception $e) { $statRatings = 0; }

/* ── Liked Videos ── */
$likedVideos = [];
try {
    $s = $pdo->prepare("SELECT v.id, v.title, v.file_path, v.uploaded_by, v.likes, v.upload_time FROM likes l JOIN videos v ON l.video_id = v.id WHERE l.user_id = ? AND v.is_active = 1 ORDER BY l.id DESC LIMIT 20");
    $s->execute([$_SESSION['user']]); $likedVideos = $s->fetchAll();
} catch(Exception $e) {}

/* ── My Videos ── */
$myVideos = [];
try {
    $s = $pdo->prepare("SELECT id, title, file_path, likes, upload_time FROM videos WHERE uploaded_by = ? AND is_active = 1 ORDER BY upload_time DESC LIMIT 20");
    $s->execute([$_SESSION['user']]); $myVideos = $s->fetchAll();
} catch(Exception $e) {}

$skinTypeLegacyMap = ['乾燥肌'=>'乾性皮','油性肌'=>'油性皮','混合肌'=>'混油皮','混合偏乾'=>'混乾皮','中性肌'=>'中性皮','敏感性肌'=>'敏感肌'];
$profileSkinType = $profile['skin_type'] ?? '';
if (isset($skinTypeLegacyMap[$profileSkinType])) $profileSkinType = $skinTypeLegacyMap[$profileSkinType];

$skinToneLegacyMap = ['中等淺'=>'中一白','偏白'=>'中二白','偏深'=>'黃三白','中等'=>'黃一白'];
$profileSkinTone = $profile['skin_tone'] ?? '';
if (isset($skinToneLegacyMap[$profileSkinTone])) $profileSkinTone = $skinToneLegacyMap[$profileSkinTone];

$skinTypes = ['乾性皮','混乾皮','中性皮','混油皮','油性皮','敏感肌'];
$skinTones = ['中一白','中二白','中三白','中性冷一白','中性冷二白','中性暖一白','中性暖二白','偏紅冷一白','偏紅冷二白','偏紅暖一白','偏紅暖二白','偏綠冷一白','偏綠冷二白','偏綠暖一白','偏綠暖二白','橄欖一白','橄欖二白','橄欖三白','粉一白','粉二白','粉三白','黃一白','黃二白','黃三白'];
$skinConcernsList = ['敏感肌','痘痘','粉刺','毛孔粗大','黑斑','細紋','皺紋','乾燥脫皮','油光滿面','暗瘡疤痕','曬斑','黑眼圈','浮腫'];
$makeupFinishList = ['霧面','水光感','自然光澤'];
$makeupStyleList  = ['日常通勤','韓系清透','歐美立體','約會精緻'];
$userInitial = mb_strtoupper(mb_substr($_SESSION['user'], 0, 1));
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 個人資料</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .profile-wrap { max-width: 820px; margin: 40px auto 80px; padding: 0 24px; }

    /* ── Header ── */
    .profile-header { display: flex; align-items: flex-start; gap: 28px; margin-bottom: 32px; position: relative; }
    .profile-avatar-wrap { position: relative; flex-shrink: 0; cursor: pointer; }
    .profile-avatar { width: 96px; height: 96px; background: var(--rose-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 700; color: var(--rose); border: 3px solid var(--rose-200); overflow: hidden; }
    .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .avatar-edit-icon { position: absolute; bottom: 2px; right: 2px; width: 28px; height: 28px; background: var(--rose); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; border: 2px solid white; }
    .avatar-upload-overlay { position: absolute; inset: 0; border-radius: 50%; background: rgba(0,0,0,.55); display: none; flex-direction: column; align-items: center; justify-content: center; gap: 5px; z-index: 10; pointer-events: none; }
    .avatar-upload-overlay.show { display: flex; }
    .avatar-spinner { width: 22px; height: 22px; border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: avatarSpin .7s linear infinite; }
    .avatar-upload-label { font-size: 10px; color: #fff; font-weight: 700; letter-spacing: .3px; }
    @keyframes avatarSpin { to { transform: rotate(360deg); } }
    .profile-info { flex: 1; min-width: 0; }
    .profile-name { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
    .profile-meta { font-size: 13px; color: var(--text-3); margin-bottom: 14px; }
    .profile-stats { display: flex; gap: 28px; }
    .profile-stat-num { font-size: 1.25rem; font-weight: 700; color: var(--rose); line-height: 1; }
    .profile-stat-label { font-size: 12px; color: var(--text-3); margin-top: 3px; }

    /* ── Tabs ── */
    .profile-tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--border); margin-bottom: 28px; }
    .profile-tab { padding: 12px 16px; font-size: 14px; font-weight: 500; color: var(--text-3); cursor: pointer; border: none; border-bottom: 2px solid transparent; background: none; font-family: inherit; transition: all var(--t); }
    .profile-tab:hover { color: var(--text); }
    .profile-tab.active { color: var(--rose); border-bottom-color: var(--rose); font-weight: 600; }

    /* ── Setting cards ── */
    .setting-card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); padding: 28px; margin-bottom: 16px; }
    .setting-card h3 { font-size: 15px; font-weight: 700; margin-bottom: 20px; }
    .setting-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media(max-width:600px){ .setting-row { grid-template-columns: 1fr; } }
    .danger-zone { border-color: var(--red-border); background: var(--red-bg); }
    .danger-zone h3 { color: var(--red); }

    /* Skin form extras */
    .radio-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .radio-pill { display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: var(--r-full); border: 1.5px solid var(--border); font-size: 13px; cursor: pointer; transition: all var(--t); }
    .radio-pill:has(input:checked) { background: var(--rose-50); border-color: var(--rose); color: var(--rose); }
    .radio-pill input { display: none; }
    .checkbox-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }
    @media(max-width:480px){ .checkbox-grid { grid-template-columns: repeat(2,1fr); } }
    .check-pill { display: flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: var(--r); border: 1.5px solid var(--border); font-size: 13px; cursor: pointer; transition: all var(--t); }
    .check-pill:has(input:checked) { background: var(--rose-50); border-color: var(--rose); color: var(--rose); }
    .check-pill input { display: none; }
    .ai-tip { background: var(--blue-bg); border-left: 4px solid var(--blue); border-radius: var(--r); padding: 14px 16px; margin-bottom: 18px; }
    .ai-tip p { font-size: 13px; color: var(--blue); margin-bottom: 10px; }
    .ai-tip strong { font-weight: 700; }

    /* Inline status */
    .inline-msg { font-size: 13px; margin-top: 10px; min-height: 18px; }
    .inline-msg.ok  { color: var(--green); }
    .inline-msg.err { color: var(--red); }
    .msg-bar { border-radius: var(--r); padding: 10px 14px; font-size: 13px; margin-bottom: 20px; }
    .msg-bar.success { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-border); }
    .msg-bar.error   { background: var(--red-bg);   color: var(--red);   border: 1px solid var(--red-border); }

    /* Video grid */
    .vid-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
    .vid-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; transition: all var(--t); }
    .vid-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
    .vid-thumb { width: 100%; aspect-ratio: 16/9; background: linear-gradient(135deg, #3d1520, #c26b7c); display: flex; align-items: center; justify-content: center; font-size: 44px; position: relative; }
    .vid-thumb video { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; }
    .vid-body { padding: 12px 14px 14px; }
    .vid-title { font-size: 14px; font-weight: 600; line-height: 1.4; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .vid-meta { font-size: 12px; color: var(--text-3); margin-bottom: 12px; }
    .vid-actions { display: flex; gap: 8px; }

    /* Modal */
    .modal-ov { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-ov.active { display: flex; }
    .modal-bx { background: var(--card); border-radius: var(--r-xl); padding: 28px; width: 90%; max-width: 420px; box-shadow: var(--shadow-lg); }
    .modal-bx h3 { font-size: 17px; font-weight: 700; margin-bottom: 18px; }
    .modal-msg { font-size: 13px; margin-top: 10px; text-align: center; min-height: 18px; }
    .modal-msg.ok  { color: var(--green); }
    .modal-msg.err { color: var(--red); }
    .reason-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    .reason-item { display: flex; align-items: center; gap: 8px; padding: 9px 12px; background: var(--bg); border-radius: var(--r); font-size: 13px; cursor: pointer; }
    .reason-item input { accent-color: var(--rose); }

    /* ── Mobile ── */
    @media (max-width: 640px) {
      .profile-wrap { padding: 0 14px; margin-top: 24px; }
      .profile-header { flex-direction: column; align-items: center; text-align: center; gap: 16px; }
      .profile-info { width: 100%; }
      .profile-stats { justify-content: center; }
      .profile-header > div:last-child { align-self: flex-end; position: absolute; top: 80px; right: 14px; }
      .profile-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; gap: 0; }
      .profile-tab { white-space: nowrap; padding: 10px 12px; }
      .setting-card { padding: 20px 16px; }
      .vid-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
    }
    @media (max-width: 480px) {
      .profile-header { padding-top: 10px; }
      .profile-avatar { width: 80px; height: 80px; font-size: 28px; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<div class="profile-wrap">

  <?php if ($isNewUser): ?>
    <div style="background:linear-gradient(135deg,#c26b7c,#7a3a8a);color:white;border-radius:var(--r-lg);padding:20px 24px;margin-bottom:24px;text-align:center;">
      <h2 style="font-size:1.2rem;margin-bottom:6px;">🎉 歡迎加入！</h2>
      <p style="font-size:13px;opacity:.85;">請填寫您的個人資料，讓我們為您提供更個人化的彩妝建議</p>
    </div>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="msg-bar <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php if ($messageType === 'success' && $isNewUser): ?>
      <script>setTimeout(()=>window.location.href='<?= BASE_URL ?>/產品/index.php',1500);</script>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Profile Header -->
  <div class="profile-header">
    <label for="avatarInput" class="profile-avatar-wrap" title="點擊更換頭貼" style="cursor:pointer;">
      <div class="profile-avatar" id="avatarCircle">
        <span id="avatarInitial"><?= $userInitial ?></span>
        <img src="<?= BASE_URL ?>/image_file.php?type=avatar&user=<?= rawurlencode($_SESSION['user']) ?>" id="avatarImg"
             style="display:none;"
             onload="this.style.display='';document.getElementById('avatarInitial').style.display='none';"
             onerror="this.style.display='none';document.getElementById('avatarInitial').style.display='';">
      </div>
      <div class="avatar-edit-icon" id="avatarEditIcon">✎</div>
      <div class="avatar-upload-overlay" id="avatarUploadOverlay">
        <div class="avatar-spinner"></div>
        <div class="avatar-upload-label">上傳中</div>
      </div>
    </label>
    <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="uploadAvatar(this)">

    <div class="profile-info">
      <div class="profile-name"><?= htmlspecialchars($_SESSION['user']) ?></div>
      <div class="profile-meta"><?= htmlspecialchars($currentEmail) ?><?= $joinedFmt ? ' · 加入於 '.$joinedFmt : '' ?></div>
      <div class="profile-stats">
        <div class="profile-stat"><div class="profile-stat-num"><?= $statLikes ?></div><div class="profile-stat-label">影片收藏</div></div>
        <div class="profile-stat"><div class="profile-stat-num"><?= $statVideos ?></div><div class="profile-stat-label">影片</div></div>
        <div class="profile-stat"><div class="profile-stat-num"><?= $statRatings ?></div><div class="profile-stat-label">評分</div></div>
      </div>
    </div>

    <div style="flex-shrink:0;">
      <a href="logout.php" class="btn btn-outline btn-sm">登出</a>
    </div>
  </div>

  <!-- Tabs -->
  <div class="profile-tabs">
    <button class="profile-tab active" onclick="switchTab(0,this)">帳號設定</button>
    <button class="profile-tab" onclick="switchTab(1,this)">我的收藏</button>
    <button class="profile-tab" onclick="switchTab(2,this)">我的影片</button>
  </div>

  <!-- ── Tab 0: Account Settings ── -->
  <div id="tab0">

    <!-- Basic Info -->
    <div class="setting-card">
      <h3>基本資料</h3>
      <div class="setting-row">
        <div class="form-group">
          <label class="form-label">帳號</label>
          <input class="form-input" value="<?= htmlspecialchars($_SESSION['user']) ?>" readonly style="background:var(--bg);color:var(--text-3);">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-input" id="emailField" type="email" value="<?= htmlspecialchars($currentEmail) ?>">
        </div>
      </div>
      <div id="emailPwdRow" style="display:none;margin-bottom:12px;">
        <label class="form-label">請輸入目前密碼以確認修改</label>
        <input class="form-input" id="emailConfirmPwd" type="password" placeholder="目前密碼" style="max-width:320px;">
      </div>
      <button class="btn btn-primary btn-sm" id="emailSendBtn" onclick="saveEmail()">寄送驗證碼</button>
      <div id="emailCodeRow" style="display:none;margin-top:12px;">
        <label class="form-label" id="emailCodeLabel">輸入驗證碼</label>
        <div style="display:flex;gap:8px;max-width:340px;">
          <input class="form-input" id="emailCode" type="text" inputmode="numeric" maxlength="6" placeholder="6 位數驗證碼">
          <button class="btn btn-primary btn-sm" onclick="emailVerify()" style="white-space:nowrap;">確認</button>
        </div>
        <div style="margin-top:8px;display:flex;gap:18px;font-size:13px;">
          <a href="#" onclick="emailResend();return false;" style="color:var(--rose);text-decoration:none;">沒收到？重新寄送</a>
          <a href="#" onclick="emailCancel();return false;" style="color:var(--text-3);text-decoration:none;">取消</a>
        </div>
      </div>
      <div class="inline-msg" id="emailMsg"></div>
    </div>

    <!-- Password -->
    <div class="setting-card">
      <h3>修改密碼</h3>
      <div class="setting-row">
        <div class="form-group">
          <label class="form-label">目前密碼</label>
          <input class="form-input" id="pwdCurrent" type="password" placeholder="輸入目前密碼">
        </div>
        <div class="form-group">
          <label class="form-label">新密碼</label>
          <input class="form-input" id="pwdNew" type="password" placeholder="至少 6 個字元">
          <div style="font-size:12px;color:#aaa;margin-top:4px;">密碼需至少 6 個字元</div>
        </div>
      </div>
      <button class="btn btn-primary btn-sm" id="pwdSendBtn" onclick="savePwd()">寄送驗證碼</button>
      <div id="pwdCodeRow" style="display:none;margin-top:12px;">
        <label class="form-label">輸入寄到信箱的驗證碼</label>
        <div style="display:flex;gap:8px;max-width:340px;">
          <input class="form-input" id="pwdCode" type="text" inputmode="numeric" maxlength="6" placeholder="6 位數驗證碼">
          <button class="btn btn-primary btn-sm" onclick="pwdVerify()" style="white-space:nowrap;">確認</button>
        </div>
        <div style="margin-top:8px;display:flex;gap:18px;font-size:13px;">
          <a href="#" onclick="pwdResend();return false;" style="color:var(--rose);text-decoration:none;">沒收到？重新寄送</a>
          <a href="#" onclick="pwdCancel();return false;" style="color:var(--text-3);text-decoration:none;">取消</a>
        </div>
      </div>
      <div class="inline-msg" id="pwdMsg"></div>
    </div>

    <!-- Skin Data -->
    <div class="setting-card">
      <h3>肌膚資料</h3>
      <form id="skinForm" method="post" onsubmit="saveSkinForm(event)">
        <input type="hidden" name="_ajax" value="1">
        <div class="ai-tip">
          <p><strong>💡 不知道自己的膚質或膚色？</strong><br>使用 AI 智慧檢測工具，快速精準分析。</p>
          <button type="button" onclick="openAITest()" class="btn btn-primary btn-sm">🤖 AI 膚色膚質檢測</button>
        </div>
        <div class="setting-row" style="margin-bottom:14px;">
          <div class="form-group">
            <label class="form-label">性別</label>
            <div class="radio-row">
              <?php foreach (['女性','男性','其他'] as $g): ?>
                <label class="radio-pill">
                  <input type="radio" name="gender" value="<?= $g ?>" <?= ($profile['gender'] ?? '') === $g ? 'checked' : '' ?>>
                  <?= $g ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">年齡</label>
            <input class="form-input" type="number" name="age" min="10" max="100" placeholder="請輸入年齡" value="<?= htmlspecialchars($profile['age'] ?? '') ?>">
          </div>
        </div>
        <div class="setting-row" style="margin-bottom:14px;">
          <div class="form-group">
            <label class="form-label">膚質</label>
            <select class="form-input" name="skin_type">
              <option value="">請選擇膚質</option>
              <?php foreach ($skinTypes as $st): ?>
                <option value="<?= $st ?>" <?= $profileSkinType === $st ? 'selected' : '' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">膚色</label>
            <select class="form-input" name="skin_tone">
              <option value="">請選擇膚色</option>
              <?php foreach ($skinTones as $st): ?>
                <option value="<?= $st ?>" <?= $profileSkinTone === $st ? 'selected' : '' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">肌膚困擾（可複選）</label>
          <div class="checkbox-grid">
            <?php foreach ($skinConcernsList as $c): ?>
              <label class="check-pill">
                <input type="checkbox" name="skin_concerns[]" value="<?= $c ?>" <?= isset($profile['skin_concerns']) && in_array($c, explode(',', str_replace(', ',',',$profile['skin_concerns']))) ? 'checked' : '' ?>>
                <?= $c ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group" style="margin-top:14px;">
          <label class="form-label">妝感偏好</label>
          <div class="radio-row">
            <?php foreach ($makeupFinishList as $f): ?>
              <label class="radio-pill">
                <input type="radio" name="makeup_finish" value="<?= $f ?>" <?= ($profile['makeup_finish'] ?? '') === $f ? 'checked' : '' ?>>
                <?= $f ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group" style="margin-top:14px;">
          <label class="form-label">妝容風格偏好</label>
          <div class="radio-row">
            <?php foreach ($makeupStyleList as $s): ?>
              <label class="radio-pill">
                <input type="radio" name="makeup_style" value="<?= $s ?>" <?= ($profile['makeup_style'] ?? '') === $s ? 'checked' : '' ?>>
                <?= $s ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group" style="margin-top:14px;">
          <label class="form-label">過敏史或敏感成分</label>
          <textarea class="form-input" name="allergies" placeholder="請列出您過敏的成分或產品（如：酒精、香精）"><?= htmlspecialchars($profile['allergies'] ?? '') ?></textarea>
        </div>
        <button type="submit" name="save_profile" class="btn btn-primary" style="width:100%;">儲存肌膚資料</button>
        <div id="skinSaveMsg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:500;text-align:center;"></div>
      </form>
    </div>

    <!-- Danger Zone -->
    <div class="setting-card danger-zone">
      <h3>危險區域</h3>
      <p style="font-size:13px;color:var(--red);margin-bottom:16px;">刪除帳號後，所有資料將永久移除，此操作無法復原。</p>
      <button class="btn btn-danger btn-sm" onclick="openDeleteModal()">刪除帳號</button>
    </div>
  </div>

  <!-- ── Tab 1: 我的收藏 ── -->
  <div id="tab1" style="display:none;">
    <?php if (!empty($likedVideos)): ?>
      <div class="vid-grid">
        <?php foreach ($likedVideos as $v): ?>
          <div class="vid-card">
            <div class="vid-thumb">
              <?php if (!empty($v['file_path'])): ?>
                <video src="<?= htmlspecialchars($v['file_path']) ?>" muted style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;"></video>
              <?php else: ?>
                🎬
              <?php endif; ?>
            </div>
            <div class="vid-body">
              <div class="vid-title"><?= htmlspecialchars($v['title'] ?? '未命名影片') ?></div>
              <div class="vid-meta"><?= htmlspecialchars($v['uploaded_by']) ?> · ❤ <?= $v['likes'] ?></div>
              <div class="vid-actions">
                <a href="<?= BASE_URL ?>/首頁/video.php" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">觀看</a>
                <form method="POST" action="<?= BASE_URL ?>/首頁/video.php">
                  <input type="hidden" name="toggle_like" value="1">
                  <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm">移除</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <div class="empty-icon">❤</div>
        <h3>還沒有收藏影片</h3>
        <p>到影片交流區按讚收藏你喜歡的影片</p>
        <a href="<?= BASE_URL ?>/首頁/video.php" class="btn btn-primary">去逛逛</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Tab 2: 我的影片 ── -->
  <div id="tab2" style="display:none;">
    <?php if (!empty($myVideos)): ?>
      <div class="vid-grid">
        <?php foreach ($myVideos as $v): ?>
          <div class="vid-card">
            <div class="vid-thumb">
              <?php if (!empty($v['file_path'])): ?>
                <video src="<?= htmlspecialchars($v['file_path']) ?>" muted style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;"></video>
              <?php else: ?>
                🎬
              <?php endif; ?>
            </div>
            <div class="vid-body">
              <div class="vid-title"><?= htmlspecialchars($v['title'] ?? '未命名影片') ?></div>
              <div class="vid-meta"><?= date('Y/m/d', strtotime($v['upload_time'])) ?> · ❤ <?= $v['likes'] ?></div>
              <div class="vid-actions">
                <a href="<?= BASE_URL ?>/首頁/video.php" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">觀看</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <div class="empty-icon">🎬</div>
        <h3>還沒有上傳影片</h3>
        <p>分享你的彩妝心得和教學影片</p>
        <a href="<?= BASE_URL ?>/首頁/video.php" class="btn btn-primary">上傳影片</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal-ov">
  <div class="modal-bx">
    <h3>⚠️ 刪除帳號</h3>
    <p style="font-size:13px;color:var(--text-2);margin-bottom:14px;">請勾選您刪除帳號的原因：</p>
    <div class="reason-list">
      <?php foreach (['不再使用此平台','找到更好的替代品','隱私或安全疑慮','功能不符合需求','帳號遭到盜用','其他原因'] as $r): ?>
        <label class="reason-item"><input type="checkbox" name="delete_reason" value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></label>
      <?php endforeach; ?>
    </div>
    <div class="form-group"><input class="form-input" id="deletePwd" type="password" placeholder="請輸入密碼確認"></div>
    <div style="display:flex;gap:10px;">
      <button class="btn btn-danger" onclick="confirmDelete()">確認刪除</button>
      <button class="btn btn-outline" onclick="closeDeleteModal()">取消</button>
    </div>
    <div class="modal-msg" id="deleteMsg"></div>
  </div>
</div>

<!-- AI Test Modal -->
<div id="aiTestModal" class="modal-ov">
  <div class="modal-bx" style="text-align:center;">
    <h3>🤖 AI 智慧檢測</h3>
    <p style="font-size:13px;color:var(--text-2);margin:12px 0 20px;">我們將使用人工智慧幫您分析膚色和膚質。</p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button class="btn btn-outline" onclick="closeAITest()">取消</button>
      <button class="btn btn-primary" onclick="startAITest()">開始檢測</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>

<script>
/* ── Skin form AJAX save ── */
async function saveSkinForm(e) {
  e.preventDefault();
  const btn = e.submitter;
  const msg = document.getElementById('skinSaveMsg');
  btn.disabled = true;
  btn.textContent = '儲存中…';
  msg.style.display = 'none';
  try {
    const data = new FormData(document.getElementById('skinForm'));
    data.append('save_profile', '1');
    const res  = await fetch('profile.php', { method: 'POST', body: data });
    const json = await res.json();
    msg.style.display = '';
    if (json.success) {
      msg.style.background = '#eef7f1'; msg.style.color = '#2d7a50'; msg.style.border = '1px solid #b2dfcc';
    } else {
      msg.style.background = '#fdf0f0'; msg.style.color = '#a05050'; msg.style.border = '1px solid #d08888';
    }
    msg.textContent = json.message;
  } catch(err) {
    msg.style.display = '';
    msg.style.background = '#fdf0f0'; msg.style.color = '#a05050'; msg.style.border = '1px solid #d08888';
    msg.textContent = '網路錯誤，請稍後再試';
  }
  btn.disabled = false;
  btn.textContent = '儲存肌膚資料';
}

/* ── Tabs ── */
const tabs = [document.getElementById('tab0'), document.getElementById('tab1'), document.getElementById('tab2')];
function switchTab(i, el) {
  document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  tabs.forEach((t,j) => t.style.display = j === i ? '' : 'none');
}

/* ── 改 Email（雙重驗證：原信箱 → 新信箱）── */
const emailField = document.getElementById('emailField');
const emailPwdRow = document.getElementById('emailPwdRow');
let emailStage = null;  // 'old' | 'new'
emailField.addEventListener('input', () => {
  emailPwdRow.style.display = emailField.value !== '<?= addslashes($currentEmail) ?>' ? '' : 'none';
});
function emShow(cls, t){ const m=document.getElementById('emailMsg'); m.className='inline-msg '+cls; m.textContent=t; }
async function emPost(p){ const r=await fetch('update_account.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(p)}); return r.json(); }
// 步驟1：寄驗證碼到「原信箱」
async function saveEmail() {
  const newEmail = emailField.value.trim();
  emShow('', '');
  if (!newEmail) return emShow('err','請填寫 Email');
  if (newEmail === '<?= addslashes($currentEmail) ?>') return emShow('ok','未做任何變更');
  const pwd = document.getElementById('emailConfirmPwd').value;
  if (!pwd) { emailPwdRow.style.display=''; return emShow('err','請輸入目前密碼'); }
  emShow('', '寄送中…');
  try {
    const d = await emPost({action:'email_request', email:newEmail, current_password:pwd});
    if (!d.success) return emShow('err', d.message);
    emailStage = 'old';
    document.getElementById('emailCodeRow').style.display = '';
    document.getElementById('emailCodeLabel').textContent = d.message;
    document.getElementById('emailCode').value = '';
    if (d.sent === false) emShow('err','⚠️ 驗證碼可能寄送失敗，請確認信箱或稍後再試'); else emShow('', '');
  } catch(e){ emShow('err','網路錯誤'); }
}
// 步驟2/3：依目前階段驗證原信箱碼 / 新信箱碼
async function emailVerify() {
  const code = document.getElementById('emailCode').value.trim();
  if (!code) return emShow('err','請輸入驗證碼');
  emShow('', '驗證中…');
  try {
    const action = emailStage === 'old' ? 'email_verify_old' : 'email_verify_new';
    const d = await emPost({action, code});
    if (!d.success) return emShow('err', d.message);
    if (d.stage === 'new') {            // 原信箱通過 → 改驗證新信箱
      emailStage = 'new';
      document.getElementById('emailCodeLabel').textContent = d.message;
      document.getElementById('emailCode').value = '';
      emShow('ok', '');
    } else if (d.stage === 'done') {    // 完成
      emShow('ok', d.message);
      document.getElementById('emailCodeRow').style.display = 'none';
      emailPwdRow.style.display = 'none';
      document.getElementById('emailConfirmPwd').value = '';
      setTimeout(()=>location.reload(), 1200);
    }
  } catch(e){ emShow('err','網路錯誤'); }
}
// 重新寄送（依目前階段寄到原/新信箱）
async function emailResend() {
  emShow('', '重新寄送中…');
  try { const d = await emPost({action:'email_resend'});
    emShow(d.success ? (d.sent===false?'err':'ok') : 'err', d.message);
  } catch(e){ emShow('err','網路錯誤'); }
}
// 取消 → 還原 email、收起驗證碼區
async function emailCancel() {
  try { await emPost({action:'email_cancel'}); } catch(e){}
  emailStage = null;
  document.getElementById('emailCodeRow').style.display = 'none';
  document.getElementById('emailCode').value = '';
  emailField.value = '<?= addslashes($currentEmail) ?>';
  emailPwdRow.style.display = 'none';
  document.getElementById('emailConfirmPwd').value = '';
  emShow('ok', '已取消，Email 維持原本的');
}

/* ── 改密碼（寄驗證碼到信箱）── */
function pwShow(cls, t){ const m=document.getElementById('pwdMsg'); m.className='inline-msg '+cls; m.textContent=t; }
async function pwPost(p){ const r=await fetch('update_account.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(p)}); return r.json(); }
// 步驟1：寄驗證碼到信箱
async function savePwd() {
  pwShow('', '');
  const cur = document.getElementById('pwdCurrent').value;
  const newPwd = document.getElementById('pwdNew').value;
  if (!cur) return pwShow('err','請輸入目前密碼');
  if (newPwd.length < 6) return pwShow('err','新密碼至少需要 6 個字元');
  pwShow('', '寄送中…');
  try {
    const d = await pwPost({action:'pwd_request', current_password:cur, new_password:newPwd});
    if (!d.success) return pwShow('err', d.message);
    document.getElementById('pwdCodeRow').style.display = '';
    document.getElementById('pwdCode').value = '';
    pwShow(d.sent === false ? 'err' : 'ok', d.sent === false ? '⚠️ 驗證碼可能寄送失敗' : d.message);
  } catch(e){ pwShow('err','網路錯誤'); }
}
// 步驟2：驗證 → 更新密碼
async function pwdVerify() {
  const code = document.getElementById('pwdCode').value.trim();
  if (!code) return pwShow('err','請輸入驗證碼');
  pwShow('', '驗證中…');
  try {
    const d = await pwPost({action:'pwd_verify', code});
    if (!d.success) return pwShow('err', d.message);
    pwShow('ok', d.message);
    ['pwdCurrent','pwdNew','pwdCode'].forEach(id => document.getElementById(id).value='');
    document.getElementById('pwdCodeRow').style.display = 'none';
  } catch(e){ pwShow('err','網路錯誤'); }
}
// 重新寄送驗證碼到信箱
async function pwdResend() {
  pwShow('', '重新寄送中…');
  try { const d = await pwPost({action:'pwd_resend'});
    pwShow(d.success ? (d.sent===false?'err':'ok') : 'err', d.message);
  } catch(e){ pwShow('err','網路錯誤'); }
}
// 取消改密碼
async function pwdCancel() {
  try { await pwPost({action:'pwd_cancel'}); } catch(e){}
  document.getElementById('pwdCodeRow').style.display = 'none';
  ['pwdCurrent','pwdNew','pwdCode'].forEach(id => document.getElementById(id).value='');
  pwShow('ok', '已取消');
}

/* ── Avatar upload ── */
async function uploadAvatar(input) {
  if (!input.files[0]) return;
  const overlay  = document.getElementById('avatarUploadOverlay');
  const editIcon = document.getElementById('avatarEditIcon');

  // 顯示「上傳中」
  overlay.innerHTML = '<div class="avatar-spinner"></div><div class="avatar-upload-label">上傳中</div>';
  overlay.style.background = 'rgba(0,0,0,.55)';
  overlay.classList.add('show');
  if (editIcon) editIcon.style.display = 'none';

  const form = new FormData();
  form.append('avatar', input.files[0]);
  try {
    const res  = await fetch('upload_avatar.php', { method:'POST', body: form });
    const data = await res.json();
    if (data.success) {
      const img  = document.getElementById('avatarImg');
      const init = document.getElementById('avatarInitial');
      img.src = data.url; img.style.display = '';
      if (init) init.style.display = 'none';

      // 顯示「✓ 成功」
      overlay.innerHTML = '<div style="font-size:26px;line-height:1;">✓</div><div class="avatar-upload-label">成功</div>';
      overlay.style.background = 'rgba(22,163,74,.72)';
      setTimeout(() => {
        overlay.classList.remove('show');
        overlay.style.background = '';
        if (editIcon) editIcon.style.display = '';
      }, 1800);
    } else {
      overlay.classList.remove('show');
      if (editIcon) editIcon.style.display = '';
      alert(data.message || '上傳失敗');
    }
  } catch(e) {
    overlay.classList.remove('show');
    if (editIcon) editIcon.style.display = '';
    alert('網路錯誤，請稍後再試');
  }
  input.value = '';
}

/* ── Delete ── */
function openDeleteModal() {
  document.querySelectorAll('#deleteModal input[type=checkbox]').forEach(c => c.checked = false);
  document.getElementById('deletePwd').value = '';
  document.getElementById('deleteMsg').textContent = '';
  document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
async function confirmDelete() {
  const reasons = Array.from(document.querySelectorAll('#deleteModal input[type=checkbox]:checked')).map(c => c.value);
  const pwd = document.getElementById('deletePwd').value;
  const msg = document.getElementById('deleteMsg');
  msg.className = 'modal-msg';
  if (reasons.length === 0) { msg.className='modal-msg err'; msg.textContent='請至少勾選一個原因'; return; }
  if (!pwd) { msg.className='modal-msg err'; msg.textContent='請輸入密碼確認'; return; }
  try {
    const res  = await fetch('delete_account.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({password:pwd,reasons}) });
    const data = await res.json();
    if (data.success) { msg.className='modal-msg ok'; msg.textContent='帳號已刪除，即將跳轉...'; setTimeout(()=>window.location.href=data.redirect,1500); }
    else { msg.className='modal-msg err'; msg.textContent=data.message; }
  } catch(e) { msg.className='modal-msg err'; msg.textContent='網路錯誤'; }
}

/* ── AI Test ── */
function openAITest() { document.getElementById('aiTestModal').classList.add('active'); }
function closeAITest() { document.getElementById('aiTestModal').classList.remove('active'); }
function startAITest() { window.location.href = '../AI/story1.php?returnTo=profile'; }

/* ── Close modals on backdrop click ── */
['deleteModal','aiTestModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) this.classList.remove('active'); });
});
</script>
</body>
</html>
