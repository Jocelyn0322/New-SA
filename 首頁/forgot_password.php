<?php
session_start();
require __DIR__ . '/../db.php';
require 'send_mail.php';

if (isset($_SESSION['user'])) {
    header("Location: /SA/New-SA/產品/index.php");
    exit();
}

if (isset($_GET['clear'])) {
    unset($_SESSION['reset_step'], $_SESSION['reset_email']);
    header("Location: forgot_password.php");
    exit();
}

$step    = $_SESSION['reset_step'] ?? 1;
$error   = '';
$success = '';

// 確保欄位存在
$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(10)");
$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expiry TIMESTAMP");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Step 1：輸入 Email，寄驗證碼 ──────────────────────────────
    if (isset($_POST['send_code'])) {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email 格式不正確';
        } else {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = '此 Email 尚未註冊';
            } else {
                $code   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

                $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?")
                    ->execute([$code, $expiry, $email]);

                if (sendPasswordResetEmail($email, $user['username'], $code)) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_step']  = 2;
                    $step    = 2;
                    $success = '驗證碼已寄送至 ' . htmlspecialchars($email);
                } else {
                    // 清除已存入的 token，避免留下殘留資料
                    $pdo->prepare("UPDATE users SET reset_token = NULL, reset_expiry = NULL WHERE email = ?")
                        ->execute([$email]);
                    $error = 'Email 寄送失敗，請確認 Gmail 設定是否正確';
                }
            }
        }
    }

    // ── Step 2：重新寄送 ──────────────────────────────────────────
    elseif (isset($_POST['resend_reset'])) {
        $email = $_SESSION['reset_email'] ?? '';
        if (!$email) { header("Location: forgot_password.php"); exit(); }

        $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $code   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?")
                ->execute([$code, $expiry, $email]);

            if (sendPasswordResetEmail($email, $user['username'], $code)) {
                $success = '驗證碼已重新寄送至 ' . htmlspecialchars($email);
            } else {
                $error = 'Email 寄送失敗';
            }
        }
        $step = 2;
    }

    // ── Step 2：輸入驗證碼 + 新密碼，完成重設 ────────────────────
    elseif (isset($_POST['reset_password'])) {
        $email      = $_SESSION['reset_email'] ?? '';
        $inputCode  = trim($_POST['code'] ?? '');
        $newPwd     = $_POST['new_password']     ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        if (!$email) { header("Location: forgot_password.php"); exit(); }

        $step = 2;

        if (strlen($newPwd) < 6) {
            $error = '密碼至少需要 6 個字元';
        } elseif ($newPwd !== $confirmPwd) {
            $error = '新密碼與確認密碼不一致';
        } else {
            $stmt = $pdo->prepare("SELECT username, reset_token, reset_expiry FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || $user['reset_token'] !== $inputCode) {
                $error = '驗證碼錯誤';
            } elseif ($user['reset_expiry'] < date("Y-m-d H:i:s")) {
                $error = '驗證碼已過期，請點「重新寄送」';
            } else {
                $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE email = ?")
                    ->execute([$newPwd, $email]);

                unset($_SESSION['reset_step'], $_SESSION['reset_email']);

                header("Location: login.php?reset=success");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>忘記密碼</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container {
            max-width: 430px;
            margin: 90px auto;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .auth-container h2 {
            font-size: 22px;
            color: #333;
            margin-bottom: 8px;
        }
        .auth-container .subtitle {
            font-size: 14px;
            color: #888;
            margin-bottom: 24px;
        }
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .auth-form input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
        }
        .auth-form input:focus {
            outline: none;
            border-color: #efc6cd;
        }
        .auth-form button[type=submit] {
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #efc6cd;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .auth-form button[type=submit]:hover { background: #e5aab4; }
        .btn-secondary {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #666;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-secondary:hover { background: #f5f5f5; }
        .msg-error   { background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px; }
        .msg-success { background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px; }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #c0606b;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        .step-info {
            background: #fff5f6;
            border-left: 3px solid #ff5a7e;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            color: #555;
            margin-bottom: 16px;
        }
        .divider { border: none; border-top: 1px solid #eee; margin: 6px 0; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<div class="auth-container">

    <?php if ($error): ?>
        <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="msg-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>

        <h2>忘記密碼</h2>
        <p class="subtitle">輸入您的 Email，我們將寄送驗證碼</p>

        <form class="auth-form" method="post">
            <input type="email" name="email" placeholder="請輸入帳號的 Email" required autofocus>
            <button type="submit" name="send_code">寄送驗證碼</button>
        </form>

    <?php else: ?>

        <h2>重設密碼</h2>
        <div class="step-info">驗證碼已寄送至 <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong>，15 分鐘內有效</div>

        <form class="auth-form" method="post">
            <input type="text" name="code" placeholder="6 位數驗證碼" maxlength="6" required autofocus inputmode="numeric">
            <hr class="divider">
            <input type="password" name="new_password" placeholder="新密碼（至少 6 字元）" required>
            <input type="password" name="confirm_password" placeholder="確認新密碼" required>
            <button type="submit" name="reset_password">確認重設密碼</button>
        </form>

        <form method="post" style="margin-top:10px;">
            <button type="submit" name="resend_reset" class="btn-secondary" style="width:100%;">重新寄送驗證碼</button>
        </form>

        <a href="forgot_password.php?clear=1" class="back-link">← 重新輸入 Email</a>

    <?php endif; ?>

    <a href="login.php" class="back-link">← 返回登入</a>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
