<?php
session_start();

require __DIR__ . '/../db.php';
require 'send_mail.php';

if (isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/產品/index.php");
    exit();
}

$error = '';
$mode = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = "請輸入帳號與密碼";
        } else {
            $stmt = $pdo->prepare("SELECT username, password, role, COALESCE(status,'active') AS status FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "帳號不存在";
            } elseif ($password !== $user['password']) {
                $error = "密碼錯誤";
            } elseif ($user['status'] === 'suspended') {
                $error = "此帳號已被停用，請聯絡管理員";
            } else {
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: " . BASE_URL . "/產品/index.php");
                exit();
            }
        }
    }

    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $mode = 'register';

        if ($username === '' || $email === '' || $password === '') {
            $error = "請完整填寫資料";
        } elseif (empty($_POST['consent'])) {
            $error = "請閱讀並勾選同意個人資料使用同意書";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email 格式不正確";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "帳號已存在";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email 已存在";
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
                    header("Location: verify_email.php");
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
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COSMETIC — 登入</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { display: flex; flex-direction: column; min-height: 100vh; }
    .auth-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
    .auth-card { background: var(--card); border-radius: var(--r-xl); border: 1px solid var(--border); box-shadow: var(--shadow-lg); width: 100%; max-width: 420px; overflow: hidden; }
    .auth-top { background: linear-gradient(135deg, #3d1520, #c26b7c); padding: 36px 40px 32px; text-align: center; color: white; }
    .auth-logo { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
    .auth-tagline { font-size: 13px; opacity: .75; }
    .auth-tabs { display: flex; border-bottom: 1px solid var(--border); }
    .auth-tab { flex: 1; padding: 14px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-3); cursor: pointer; border-bottom: 2px solid transparent; text-decoration: none; transition: all var(--t); }
    .auth-tab.active { color: var(--rose); border-bottom-color: var(--rose); }
    .auth-body { padding: 28px 32px 32px; }
    .forgot-link { display: block; text-align: right; font-size: 12px; color: var(--rose); margin-top: 6px; }
    .auth-error { background: var(--red-bg); color: var(--red); border: 1px solid var(--red-border); border-radius: var(--r); padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
    .auth-success { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-border); border-radius: var(--r); padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
    .consent-row { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--text-2); line-height: 1.5; margin-bottom: 16px; }
    .consent-row input[type="checkbox"] { margin-top: 3px; flex-shrink: 0; width: 15px; height: 15px; accent-color: var(--rose); }
    .consent-link { color: var(--rose); cursor: pointer; text-decoration: underline; background: none; border: none; padding: 0; font-size: 13px; font-family: inherit; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #fff; border-radius: var(--r-xl); padding: 32px; max-width: 560px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; box-shadow: var(--shadow-lg); }
    .modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 16px; }
    .modal-box p, .modal-box li { font-size: 13px; color: var(--text-2); line-height: 1.8; margin-bottom: 10px; }
    .modal-box ul { padding-left: 20px; margin-bottom: 10px; }
    .modal-close { position: absolute; top: 14px; right: 18px; font-size: 20px; background: none; border: none; cursor: pointer; color: var(--text-3); }
  </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-top">
      <div class="auth-logo">💄 COSMETIC</div>
      <div class="auth-tagline">找到最適合你的彩妝</div>
    </div>

    <div class="auth-tabs">
      <a href="login.php?mode=login"    class="auth-tab <?= $mode === 'login'    ? 'active' : '' ?>">登入</a>
      <a href="login.php?mode=register" class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>">註冊</a>
    </div>

    <div class="auth-body">
      <?php if ($error): ?>
        <div class="auth-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($mode === 'register'): ?>
        <form method="post">
          <input type="hidden" name="action" value="register">
          <div class="form-group"><label class="form-label">帳號</label><input class="form-input" type="text" name="username" placeholder="設定帳號（英數字）" required></div>
          <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" placeholder="your@email.com" required></div>
          <div class="form-group"><label class="form-label">密碼</label><input class="form-input" type="password" name="password" placeholder="至少 8 個字元" required></div>
          <div class="consent-row">
            <input type="checkbox" name="consent" id="consent" value="1" required>
            <label for="consent">我已閱讀並同意
              <button type="button" class="consent-link" onclick="document.getElementById('consentModal').classList.add('active')">《個人資料及臉部照片使用同意書》</button>
            </label>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;">註冊並寄送驗證碼</button>
        </form>
      <?php else: ?>
        <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
          <div class="auth-success">密碼已重設成功，請使用新密碼登入</div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <div class="form-group"><label class="form-label">帳號</label><input class="form-input" type="text" name="username" placeholder="輸入帳號" required></div>
          <div class="form-group">
            <label class="form-label">密碼</label>
            <input class="form-input" type="password" name="password" placeholder="輸入密碼" required>
            <a href="forgot_password.php" class="forgot-link">忘記密碼？</a>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:4px;">登入</button>
        </form>
      <?php endif; ?>
    </div>
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
    <button type="button" class="btn btn-primary" style="width:100%;margin-top:8px;"
      onclick="document.getElementById('consent').checked=true;document.getElementById('consentModal').classList.remove('active')">
      我已閱讀，關閉視窗
    </button>
  </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
