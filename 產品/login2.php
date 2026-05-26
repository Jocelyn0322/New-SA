<?php
session_start();
require_once __DIR__ . '/../db.php';

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// 處理註冊
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($username) || empty($password)) {
        $error = "請填寫所有欄位";
    } elseif (strlen($password) < 6) {
        $error = "密碼至少需要6個字元";
    } elseif ($password !== $confirmPassword) {
        $error = "兩次密碼不一致";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = "帳號已存在";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
            if ($stmt->execute([$username, $password])) {
                $_SESSION['user'] = $username;
                $_SESSION['role'] = 'user';
                header("Location: profile.php?new=1");
                exit();
            } else {
                $error = "註冊失敗，請稍後再試";
            }
        }
    }
}

// 處理登入
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        if ($password === $user['password']) {
            $_SESSION['user'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                echo "<script>alert('登入成功'); window.location.href='index.php';</script>";
                exit();
            }

            $stmt2 = $pdo->prepare("SELECT id FROM user_profiles WHERE username = ?");
            $stmt2->execute([$username]);
            if (!$stmt2->fetch()) {
                header("Location: profile.php?new=1");
                exit();
            }

            echo "<script>alert('登入成功'); window.location.href='index.php';</script>";
            exit();
        } else {
            $error = "密碼錯誤";
        }
    } else {
        $error = "帳號不存在";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .login-form {
            display: flex;
            flex-direction: column;
        }
        .login-form input {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .login-form button {
            padding: 10px;
            background: #efc6cd;
            color: #111;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .error {
            color: red;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>

    <div class="login-container">
        <h2 style="text-align: center; margin-bottom: 30px;">登入</h2>

        <form class="login-form" method="post">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <input type="text" name="username" placeholder="用戶名" required>
            <input type="password" name="password" placeholder="密碼" required>
            <button type="submit" name="login">登入</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            還沒有帳號？ <a href="#" onclick="showRegisterForm(); return false;" style="color: #ff5a7e;">立即註冊</a>
        </p>
    </div>

    <!-- 註冊表單 -->
    <div class="login-container" id="registerContainer" style="display: none;">
        <h2 style="text-align: center; margin-bottom: 30px;">註冊</h2>

        <form class="login-form" method="post">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <input type="text" name="username" placeholder="用戶名" required>
            <input type="password" name="password" placeholder="密碼（至少6個字元）" required>
            <input type="password" name="confirm_password" placeholder="確認密碼" required>
            <button type="submit" name="register">註冊</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            已有帳號？ <a href="#" onclick="showLoginForm(); return false;" style="color: #ff5a7e;">立即登入</a>
        </p>
    </div>

    <script>
        function showRegisterForm() {
            document.querySelector('.login-container').style.display = 'none';
            document.getElementById('registerContainer').style.display = 'block';
        }
        
        function showLoginForm() {
            document.getElementById('registerContainer').style.display = 'none';
            document.querySelector('.login-container').style.display = 'block';
        }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>