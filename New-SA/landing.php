<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/首頁/send_mail.php';

// 已登入直接進首頁
if (isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/產品/index.php");
    exit();
}

$error = '';
$mode  = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 登入 ──────────────────────────────────────
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = '請輸入帳號與密碼';
        } else {
            $stmt = $pdo->prepare("SELECT username, password, role, COALESCE(status,'active') AS status FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = '帳號不存在';
            } elseif ($password !== $user['password']) {
                $error = '密碼錯誤';
            } elseif ($user['status'] === 'suspended') {
                $error = '此帳號已被停用，請聯絡管理員';
            } else {
                $_SESSION['user']          = $user['username'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['_session_init'] = true;
                header("Location: " . BASE_URL . "/產品/index.php");
                exit();
            }
        }
    }

    // ── 註冊 ──────────────────────────────────────
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $mode     = 'register';

        if ($username === '' || $email === '' || $password === '') {
            $error = '請完整填寫資料';
        } elseif (strlen($password) < 6) {
            $error = '密碼至少需要 6 個字元';
        } elseif (empty($_POST['consent'])) {
            $error = '請閱讀並勾選同意個人資料使用同意書';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email 格式不正確';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = '帳號已存在';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email 已存在';
                } else {
                    $code   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
                    $_SESSION['pending_user']     = $username;
                    $_SESSION['pending_email']    = $email;
                    $_SESSION['pending_password'] = $password;
                    $_SESSION['pending_code']     = $code;
                    $_SESSION['pending_expiry']   = $expiry;
                    $emailSent = sendVerificationEmail($email, $username, $code);
                    if (!$emailSent) $_SESSION['email_send_failed'] = true;
                    header("Location: " . BASE_URL . "/首頁/verify_email.php");
                    exit();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 找到最適合你的彩妝</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --rose:    #c26b7c;
      --rose-d:  #9d2942;
      --wine:    #6b1e2e;
      --text:    #1a1a2e;
      --text-2:  #555;
      --text-3:  #999;
      --border:  #ede9f4;
      --bg:      #faf9fc;
      --card:    #fff;
      --r:       10px;
      --r-xl:    20px;
      --r-full:  99px;
      --t:       .18s ease;
    }
    body { font-family: system-ui, -apple-system, "Segoe UI", "PingFang TC", "Microsoft JhengHei", "Noto Sans TC", sans-serif; min-height: 100vh; display: flex; background: var(--bg); color: var(--text); }

    /* ── LEFT HERO ── */
    .hero {
      flex: 1;
      background: linear-gradient(145deg, #3d1520 0%, #6b1e2e 40%, #c26b7c 100%);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 60px 56px;
      position: relative;
      overflow: hidden;
      min-height: 100vh;
    }
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      pointer-events: none;
    }
    .hero-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 56px;
    }
    .hero-brand-icon { font-size: clamp(1.2rem, 7vw, 2rem); }
    .hero-brand-name { font-size: clamp(1.8rem, 13vw, 3.75rem); font-weight: 800; color: #fff; letter-spacing: .08em; }

    .hero-title {
      font-size: clamp(2rem, 3.5vw, 2.8rem);
      font-weight: 900;
      color: #fff;
      line-height: 1.25;
      margin-bottom: 20px;
    }
    .hero-title span { color: #f9c8d4; }

    .hero-desc {
      font-size: 15px;
      color: rgba(255,255,255,.75);
      line-height: 1.8;
      max-width: 420px;
      margin-bottom: 48px;
    }

    .hero-features { display: flex; flex-direction: column; gap: 18px; }
    .hero-feature {
      display: flex;
      align-items: center;
      gap: 14px;
      color: rgba(255,255,255,.9);
    }
    .hero-feature-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: rgba(255,255,255,.15);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .hero-feature-text strong { display: block; font-size: 14px; font-weight: 700; }
    .hero-feature-text span { font-size: 12px; opacity: .7; }

    .hero-deco {
      position: absolute;
      bottom: -60px;
      right: -60px;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(255,255,255,.06);
    }
    .hero-deco2 {
      position: absolute;
      top: -40px;
      right: 60px;
      width: 180px;
      height: 180px;
      border-radius: 50%;
      background: rgba(255,255,255,.04);
    }

    /* ── RIGHT AUTH PANEL ── */
    .auth-panel {
      width: 460px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      background: var(--card);
      box-shadow: -4px 0 40px rgba(0,0,0,.06);
      min-height: 100vh;
      overflow-y: auto;
    }
    .auth-inner { width: 100%; max-width: 360px; }

    .auth-greeting { font-size: 1.6rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
    .auth-sub { font-size: 14px; color: var(--text-3); margin-bottom: 32px; }

    /* tabs */
    .auth-tabs {
      display: flex;
      gap: 0;
      background: var(--bg);
      border-radius: var(--r-full);
      padding: 4px;
      margin-bottom: 28px;
    }
    .auth-tab {
      flex: 1;
      padding: 9px;
      text-align: center;
      font-size: 14px;
      font-weight: 600;
      color: var(--text-3);
      border-radius: var(--r-full);
      text-decoration: none;
      transition: all var(--t);
    }
    .auth-tab.active {
      background: var(--card);
      color: var(--rose);
      box-shadow: 0 1px 8px rgba(0,0,0,.1);
    }

    /* form */
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
    .form-input {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--r);
      font-size: 14px;
      font-family: inherit;
      color: var(--text);
      background: var(--bg);
      outline: none;
      transition: border var(--t), background var(--t);
    }
    .form-input:focus { border-color: var(--rose); background: #fff; }
    .form-input::placeholder { color: var(--text-3); }

    .forgot-link { display: block; text-align: right; font-size: 12px; color: var(--rose); margin-top: 5px; text-decoration: none; }
    .forgot-link:hover { text-decoration: underline; }

    /* btn */
    .btn-submit {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, var(--wine), var(--rose));
      color: #fff;
      border: none;
      border-radius: var(--r-full);
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 6px;
      transition: opacity var(--t), transform var(--t);
    }
    .btn-submit:hover { opacity: .9; transform: translateY(-1px); }

    /* error / success */
    .auth-error {
      background: #fff2f4;
      color: #c0392b;
      border: 1px solid #f5c6cb;
      border-radius: var(--r);
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 16px;
    }
    .auth-success {
      background: #f0fff4;
      color: #27ae60;
      border: 1px solid #c3e6cb;
      border-radius: var(--r);
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 16px;
    }

    /* consent */
    .consent-row { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--text-2); line-height: 1.5; margin-bottom: 16px; }
    .consent-row input[type="checkbox"] { margin-top: 3px; flex-shrink: 0; width: 15px; height: 15px; accent-color: var(--rose); }
    .consent-link { color: var(--rose); cursor: pointer; text-decoration: underline; background: none; border: none; padding: 0; font-size: 13px; font-family: inherit; }

    /* modal */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #fff; border-radius: var(--r-xl); padding: 32px; max-width: 560px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
    .modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 16px; }
    .modal-box p, .modal-box li { font-size: 13px; color: var(--text-2); line-height: 1.8; margin-bottom: 10px; }
    .modal-box ul { padding-left: 20px; margin-bottom: 10px; }
    .modal-close { position: absolute; top: 14px; right: 18px; font-size: 20px; background: none; border: none; cursor: pointer; color: var(--text-3); }
    .modal-confirm-btn { width: 100%; padding: 11px; background: linear-gradient(135deg, var(--wine), var(--rose)); color: #fff; border: none; border-radius: var(--r-full); font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 8px; }

    /* responsive */
    @media (max-width: 768px) {
      body { flex-direction: column; }
      .hero { min-height: auto; padding: 40px 28px; }
      .hero-features { display: none; }
      .auth-panel { width: 100%; min-height: auto; box-shadow: none; padding: 36px 24px; }
    }

    /* ── Animations ── */
    @keyframes fadeInUp  { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeInLeft{ from{opacity:0;transform:translateX(-28px)} to{opacity:1;transform:translateX(0)} }
    @keyframes fadeInRight{from{opacity:0;transform:translateX(28px)} to{opacity:1;transform:translateX(0)} }
    @keyframes pulseDeco { 0%,100%{opacity:.06;transform:scale(1)} 50%{opacity:.14;transform:scale(1.08)} }
    @keyframes shimmerShine { from{transform:translateX(-100%)} to{transform:translateX(200%)} }
    @keyframes gradFlow {
      0%,100%{background-position:0% 50%}
      50%{background-position:100% 50%}
    }

    /* animated gradient hero bg */
    .hero {
      background: linear-gradient(145deg, #3d1520, #6b1e2e, #a04060, #c26b7c, #6b1e2e, #3d1520);
      background-size: 300% 300%;
      animation: gradFlow 12s ease infinite;
    }

    /* deco circles */
    .hero-deco  { animation: pulseDeco 5s ease-in-out infinite; }
    .hero-deco2 { animation: pulseDeco 7s 1.5s ease-in-out infinite; }

    /* fireworks canvas */
    .fw-canvas {
      position: absolute;
      inset: 0;
      width: 100%; height: 100%;
      pointer-events: none;
      z-index: 0;
    }

    /* entrance animations */
    .anim-brand    { animation: fadeInLeft  .8s .05s ease both; }
    .anim-title    { animation: fadeInUp    .8s .15s ease both; }
    .anim-desc     { animation: fadeInUp    .7s .25s ease both; }
    .anim-feat-1   { animation: fadeInUp    .6s .35s ease both; }
    .anim-feat-2   { animation: fadeInUp    .6s .45s ease both; }
    .anim-feat-3   { animation: fadeInUp    .6s .55s ease both; }
    .anim-feat-4   { animation: fadeInUp    .6s .65s ease both; }
    .anim-panel    { animation: fadeInRight .75s .1s  ease both; }

    /* btn shimmer */
    .btn-submit { position:relative; overflow:hidden; }
    .btn-submit::after {
      content:'';
      position:absolute; inset:0;
      background:linear-gradient(105deg,transparent 35%,rgba(255,255,255,.28) 50%,transparent 65%);
      transform:translateX(-100%);
      transition:transform .45s ease;
      pointer-events:none;
    }
    .btn-submit:hover::after { transform:translateX(120%); }

    @media (prefers-reduced-motion: reduce) {
      .hero { animation: none; }
      [class*="anim-"] { animation: none; opacity:1; }
    }
  </style>
</head>
<body>

  <!-- ── LEFT HERO ── -->
  <div class="hero">
    <div class="hero-deco"></div>
    <div class="hero-deco2"></div>
    <!-- canvas 煙火 -->
    <canvas class="fw-canvas"></canvas>

    <div class="hero-brand anim-brand">
      <span class="hero-brand-icon">💄</span>
      <span class="hero-brand-name">COSMETIC</span>
    </div>

    <div class="hero-title anim-title">
      找到<span>最適合你</span><br>的彩妝世界
    </div>
    <p class="hero-desc anim-desc">
      AI 膚色分析、智慧產品推薦、影片交流社群，<br>
      一站式彩妝探索平台，讓每次上妝都更有自信。
    </p>

    <div class="hero-features">
      <div class="hero-feature anim-feat-1">
        <div class="hero-feature-icon">🤖</div>
        <div class="hero-feature-text">
          <strong>AI 膚色檢測</strong>
          <span>上傳照片，精準分析最適合你的色號</span>
        </div>
      </div>
      <div class="hero-feature anim-feat-2">
        <div class="hero-feature-icon">🌤️</div>
        <div class="hero-feature-text">
          <strong>天氣－產品推薦</strong>
          <span>依當日天氣智慧推薦最佳彩妝品</span>
        </div>
      </div>
      <div class="hero-feature anim-feat-3">
        <div class="hero-feature-icon">⚖️</div>
        <div class="hero-feature-text">
          <strong>多產品比較</strong>
          <span>成分、色號、品牌一目了然</span>
        </div>
      </div>
      <div class="hero-feature anim-feat-4">
        <div class="hero-feature-icon">🎬</div>
        <div class="hero-feature-text">
          <strong>影片交流社群</strong>
          <span>分享彩妝技巧、與同好互動</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ── RIGHT AUTH ── -->
  <div class="auth-panel anim-panel">
    <div class="auth-inner">
      <div class="auth-greeting"><?= $mode === 'register' ? '建立帳號 👋' : '歡迎回來 👋' ?></div>
      <div class="auth-sub"><?= $mode === 'register' ? '填寫資料，開啟彩妝探索之旅' : '登入以使用所有功能' ?></div>

      <div class="auth-tabs">
        <a href="landing.php?mode=login"    class="auth-tab <?= $mode === 'login'    ? 'active' : '' ?>">登入</a>
        <a href="landing.php?mode=register" class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>">註冊</a>
      </div>

      <?php if ($error): ?>
        <div class="auth-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
        <div class="auth-success">✅ 密碼已重設成功，請使用新密碼登入</div>
      <?php endif; ?>

      <?php if ($mode === 'register'): ?>
        <form method="post">
          <input type="hidden" name="action" value="register">
          <div class="form-group">
            <label class="form-label">帳號</label>
            <input class="form-input" type="text" name="username" placeholder="設定帳號（暱稱）" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" placeholder="your@email.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">密碼</label>
            <input class="form-input" type="password" name="password" placeholder="至少 6 個字元" required minlength="6">
            <div style="font-size:12px;color:#aaa;margin-top:4px;">密碼需至少 6 個字元</div>
          </div>
          <div class="consent-row">
            <input type="checkbox" name="consent" id="consent" value="1" required>
            <label for="consent">我已閱讀並同意
              <button type="button" class="consent-link" onclick="document.getElementById('consentModal').classList.add('active')">
                《個人資料及臉部照片使用同意書》
              </button>
            </label>
          </div>
          <button type="submit" class="btn-submit">註冊並寄送驗證碼</button>
        </form>

      <?php else: ?>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <div class="form-group">
            <label class="form-label">帳號名稱</label>
            <input class="form-input" type="text" name="username" placeholder="輸入帳號名稱（暱稱）" required autofocus>
          </div>
          <div class="form-group">
            <label class="form-label">密碼</label>
            <input class="form-input" type="password" name="password" placeholder="輸入密碼" required>
            <a href="<?= BASE_URL ?>/首頁/forgot_password.php" class="forgot-link">忘記密碼？</a>
          </div>
          <button type="submit" class="btn-submit">登入</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

<!-- 同意書 Modal -->
<div class="modal-overlay" id="consentModal">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('consentModal').classList.remove('active')">&times;</button>
    <h3>個人資料及臉部照片使用同意書</h3>
    <p>親愛的使用者，本網站依據《個人資料保護法》，在蒐集、處理及利用您的個人資料前，請您詳閱以下說明：</p>
    <p><strong>一、蒐集之個人資料類別</strong></p>
    <ul><li>基本識別資料：帳號、電子郵件</li><li>生物特徵資料：臉部照片及相關辨識資訊</li></ul>
    <p><strong>二、蒐集目的</strong></p>
    <ul><li>提供 AI 彩妝模擬、妝容推薦等個人化服務</li><li>帳號驗證與會員管理</li><li>改善網站功能與使用者體驗</li></ul>
    <p><strong>三、資料利用範圍</strong></p>
    <ul><li>您的臉部照片僅用於本網站提供之彩妝模擬功能</li><li>不會提供給第三方廣告商或其他商業用途</li><li>不會跨境傳輸您的個人資料</li></ul>
    <p><strong>四、資料保存期間</strong></p>
    <p>您的個人資料將於帳號有效期間內保存，帳號刪除後將於 30 天內一併刪除。</p>
    <p><strong>五、您的權利</strong></p>
    <ul><li>您可隨時查詢、補充、更正或要求刪除您的個人資料</li><li>您可選擇不提供，但部分服務功能將因此受限</li></ul>
    <p><strong>六、同意聲明</strong></p>
    <p>勾選同意即表示您已詳閱上述說明，並同意本網站依前述目的蒐集、處理及利用您的個人資料（包含臉部照片）。</p>
    <button class="modal-confirm-btn"
      onclick="document.getElementById('consent').checked=true;document.getElementById('consentModal').classList.remove('active')">
      我已閱讀，關閉視窗
    </button>
  </div>
</div>

<script src="<?= BASE_URL ?>/fireworks.js"></script>
<script src="<?= BASE_URL ?>/interactions.js"></script>
</body>
</html>
