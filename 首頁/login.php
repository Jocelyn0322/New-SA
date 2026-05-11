<?php
session_start();

require 'send_mail.php';

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$mode = $_GET['mode'] ?? 'login';

$conn = new mysqli(
    "localhost", 
    "root", 
    "", 
    "sa_db",
    3306,
    "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"
);

if ($conn->connect_error) {
    die("資料庫連線失敗：" . $conn->connect_error);
}

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
            $stmt = $conn->prepare("
                SELECT username, email, password, role, email_verified, verification_code, verification_expiry
                FROM users
                WHERE username = ?
            ");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = "帳號不存在";
            } elseif ($password !== $user['password']) {
                $error = "密碼錯誤";
            } elseif ((int)$user['email_verified'] !== 1) {
                $newCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

                $stmt = $conn->prepare("
                    UPDATE users
                    SET verification_code = ?, verification_expiry = ?
                    WHERE username = ?
                ");
                $stmt->bind_param("sss", $newCode, $expiry, $username);
                $stmt->execute();
                $stmt->close();

                sendVerificationEmail($user['email'], $username, $newCode);

                $_SESSION['pending_user'] = $username;
                $_SESSION['pending_email'] = $user['email'];

                header("Location: verify_email.php");
                exit();
            } else {
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                header("Location: index.php");
                exit();
            }
        }
    }

    // =========================
    // 註冊
    // =========================
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $mode = 'register';

        if ($username === '' || $email === '' || $password === '') {
            $error = "請完整填寫資料";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email 格式不正確";
        } else {
            // 檢查帳號
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $usernameResult = $stmt->get_result();
            $stmt->close();

            if ($usernameResult->num_rows > 0) {
                $error = "帳號已存在";
            } else {
                // 檢查 Email
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $emailResult = $stmt->get_result();
                $stmt->close();

                if ($emailResult->num_rows > 0) {
                    $error = "Email 已存在";
                } else {
                    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

                    $stmt = $conn->prepare("
                        INSERT INTO users
                        (username, email, password, role, email_verified, verification_code, verification_expiry)
                        VALUES (?, ?, ?, 'user', 0, ?, ?)
                    ");
                    $stmt->bind_param("sssss", $username, $email, $password, $code, $expiry);

                    if ($stmt->execute()) {
                        $stmt->close();

                        if (sendVerificationEmail($email, $username, $code)) {
                            $_SESSION['pending_user'] = $username;
                            $_SESSION['pending_email'] = $email;

                            header("Location: verify_email.php");
                            exit();
                        } else {
                            $error = "註冊成功，但驗證信寄送失敗，請稍後再試";
                        }
                    } else {
                        $error = "註冊失敗，請重試";
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>登入 / 註冊</title>
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

            <button type="submit">註冊並寄送驗證碼</button>
        </form>
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