<?php
session_start();

require 'db.php';
require 'send_mail.php';

if (isset($_SESSION['user'])) {
    header("Location: /SA/New-SA/產品/index.php");
    exit();
}

$error = '';
$mode = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // =========================
    // 登入
    // =========================
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = "請輸入帳號與密碼";
        } else {
            $stmt = $pdo->prepare("SELECT username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "帳號不存在";
            } elseif ($password !== $user['password']) {
                $error = "密碼錯誤";
            } else {
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                header("Location: /SA/New-SA/產品/index.php");
                exit();
            }
        }
    }

    // =========================
    // 註冊
    // =========================
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

                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, role, email_verified, verification_code, verification_expiry)
                        VALUES (?, ?, ?, 'user', 0, ?, ?)
                    ");
                    if ($stmt->execute([$username, $email, $password, $code, $expiry])) {
                        $emailSent = sendVerificationEmail($email, $username, $code);

                        $_SESSION['pending_user']  = $username;
                        $_SESSION['pending_email'] = $email;
                        if (!$emailSent) {
                            $_SESSION['email_send_failed'] = true;
                        }

                        header("Location: verify_email.php");
                        exit();
                    } else {
                        $error = "註冊失敗，請重試";
                    }
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
    <title>登入 / 註冊</title>
    <link rel="stylesheet" href="style.css?v=2">
    <style>
        .auth-container {
            max-width: 430px;
            margin: 90px auto;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .auth-tabs {
            display: flex;
            margin-bottom: 28px;
            gap: 10px;
        }

        .auth-tabs a {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            background: #eee;
        }

        .auth-tabs a.active {
            background: #efc6cd;
            font-weight: bold;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .auth-form input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        .auth-form button {
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #efc6cd;
            cursor: pointer;
            font-size: 16px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .consent-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 14px;
            color: #444;
            line-height: 1.5;
        }

        .consent-row input[type="checkbox"] {
            margin-top: 3px;
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .consent-link {
            color: #c0606b;
            cursor: pointer;
            text-decoration: underline;
            background: none;
            border: none;
            padding: 0;
            font-size: 14px;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 560px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-box h3 {
            margin: 0 0 16px;
            font-size: 18px;
            color: #333;
        }

        .modal-box p, .modal-box li {
            font-size: 14px;
            color: #555;
            line-height: 1.8;
        }

        .modal-box ul {
            padding-left: 20px;
        }

        .modal-close {
            position: absolute;
            top: 14px;
            right: 18px;
            font-size: 22px;
            background: none;
            border: none;
            cursor: pointer;
            color: #888;
        }

        .modal-close:hover { color: #333; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="auth-container">
    <div class="auth-tabs">
        <a href="login.php?mode=login" class="<?php echo $mode === 'login' ? 'active' : ''; ?>">登入</a>
        <a href="login.php?mode=register" class="<?php echo $mode === 'register' ? 'active' : ''; ?>">註冊</a>
    </div>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($mode === 'register'): ?>
        <form class="auth-form" method="post">
            <input type="hidden" name="action" value="register">

            <input type="text" name="username" placeholder="帳號" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="密碼" required>

            <div class="consent-row">
                <input type="checkbox" name="consent" id="consent" value="1" required>
                <label for="consent">
                    我已閱讀並同意
                    <button type="button" class="consent-link" onclick="document.getElementById('consentModal').classList.add('active')">
                        《個人資料及臉部照片使用同意書》
                    </button>
                </label>
            </div>

            <button type="submit">註冊並寄送驗證碼</button>
        </form>

        <!-- 同意書 Modal -->
        <div class="modal-overlay" id="consentModal">
            <div class="modal-box">
                <button class="modal-close" onclick="document.getElementById('consentModal').classList.remove('active')">&times;</button>
                <h3>個人資料及臉部照片使用同意書</h3>
                <p>親愛的使用者，本網站依據《個人資料保護法》，在蒐集、處理及利用您的個人資料前，請您詳閱以下說明：</p>

                <p><strong>一、蒐集之個人資料類別</strong></p>
                <ul>
                    <li>基本識別資料：帳號、電子郵件</li>
                    <li>生物特徵資料：臉部照片及相關辨識資訊</li>
                </ul>

                <p><strong>二、蒐集目的</strong></p>
                <ul>
                    <li>提供 AI 彩妝模擬、妝容推薦等個人化服務</li>
                    <li>帳號驗證與會員管理</li>
                    <li>改善網站功能與使用者體驗</li>
                </ul>

                <p><strong>三、資料利用範圍</strong></p>
                <ul>
                    <li>您的臉部照片僅用於本網站提供之彩妝模擬功能</li>
                    <li>不會提供給第三方廣告商或其他商業用途</li>
                    <li>不會跨境傳輸您的個人資料</li>
                </ul>

                <p><strong>四、資料保存期間</strong></p>
                <p>您的個人資料將於帳號有效期間內保存，帳號刪除後將於 30 天內一併刪除。</p>

                <p><strong>五、您的權利</strong></p>
                <ul>
                    <li>您可隨時查詢、補充、更正或要求刪除您的個人資料</li>
                    <li>您可選擇不提供，但部分服務功能將因此受限</li>
                </ul>

                <p><strong>六、同意聲明</strong></p>
                <p>勾選同意即表示您已詳閱上述說明，並同意本網站依前述目的蒐集、處理及利用您的個人資料（包含臉部照片）。</p>

                <button type="button" style="margin-top:16px;width:100%;padding:10px;background:#efc6cd;border:none;border-radius:8px;font-size:15px;cursor:pointer;"
                    onclick="document.getElementById('consent').checked=true; document.getElementById('consentModal').classList.remove('active')">
                    我已閱讀，關閉視窗
                </button>
            </div>
        </div>
    <?php else: ?>
        <form class="auth-form" method="post">
            <input type="hidden" name="action" value="login">

            <input type="text" name="username" placeholder="帳號" required>
            <input type="password" name="password" placeholder="密碼" required>

            <button type="submit">登入</button>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

</body>
</html>