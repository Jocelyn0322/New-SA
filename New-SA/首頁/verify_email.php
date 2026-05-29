<?php
session_start();

if (!isset($_SESSION['pending_user']) || !isset($_SESSION['pending_email'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['pending_user'];
$email = $_SESSION['pending_email'];

$error = '';
$success = '';

require __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_code'])) {
        $inputCode      = trim($_POST['verify_code']);
        $sessionCode    = $_SESSION['pending_code']   ?? '';
        $sessionExpiry  = $_SESSION['pending_expiry'] ?? '';
        $sessionPwd     = $_SESSION['pending_password'] ?? '';

        if (!$sessionCode) {
            $error = "驗證資料已失效，請重新註冊";
        } elseif ($inputCode !== $sessionCode) {
            $error = "驗證碼錯誤";
        } elseif ($sessionExpiry < date("Y-m-d H:i:s")) {
            $error = "驗證碼已過期，請點「重新寄送」";
        } else {
            // 驗證成功，才寫入資料庫
            $pdo->prepare("
                INSERT INTO users (username, email, password, role, email_verified)
                VALUES (?, ?, ?, 'user', 1)
            ")->execute([$username, $email, $sessionPwd]);

            $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $newUser = $stmt->fetch();

            $_SESSION['user'] = $username;
            $_SESSION['role'] = $newUser['role'] ?? 'user';

            unset($_SESSION['pending_user'], $_SESSION['pending_email'],
                  $_SESSION['pending_password'], $_SESSION['pending_code'],
                  $_SESSION['pending_expiry'], $_SESSION['email_send_failed']);

            header("Location: " . BASE_URL . "/產品/index.php");
            exit();
        }
    }

    if (isset($_POST['resend_code'])) {
        require_once 'send_mail.php';

        $newCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry  = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        // 更新 Session，不碰資料庫
        $_SESSION['pending_code']   = $newCode;
        $_SESSION['pending_expiry'] = $expiry;

        if (sendVerificationEmail($email, $username, $newCode)) {
            $success = "驗證碼已重新寄送至 " . htmlspecialchars($email);
            unset($_SESSION['email_send_failed']);
        } else {
            $error = "驗證信寄送失敗，請確認 Gmail 設定是否正確";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>Email 驗證</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .verify-container {
            max-width: 500px;
            margin: 90px auto;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            text-align: center;
        }

        .verify-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 20px;
        }

        .verify-form input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
        }

        .verify-form button {
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #efc6cd;
            cursor: pointer;
            font-size: 16px;
        }

        .verify-form button.secondary {
            background: #6c757d;
            color: white;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 14px;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
        }

        .note {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<div class="verify-container">
    <h2>Email 驗證</h2>

    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['email_send_failed'])): ?>
        <div class="message error">
            驗證信寄送失敗（Gmail 尚未設定），請點「重新寄送驗證碼」，或確認 send_mail.php 的 Gmail 帳密設定後再試。
        </div>
        <?php unset($_SESSION['email_send_failed']); ?>
    <?php endif; ?>

    <p class="note">
        帳號：<?php echo htmlspecialchars($username); ?><br>
        驗證碼已寄送至：<?php echo htmlspecialchars($email); ?>
    </p>

    <form class="verify-form" method="post">
        <input type="text" name="verify_code" placeholder="輸入驗證碼" maxlength="6" required>
        <button type="submit">確認驗證</button>
    </form>

    <form class="verify-form" method="post">
        <button type="submit" name="resend_code" class="secondary">重新寄送驗證碼</button>
    </form>
</div>

</body>
</html>