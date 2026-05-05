<?php
session_start();

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $conn = new mysqli("localhost", "root", "", "sa_db");

    if ($conn->connect_error) {
        die("資料庫連線失敗：" . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ($password === $user['password']) {
            $_SESSION['user'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            echo "<script>
                alert('登入成功');
                window.location.href='index.php';
            </script>";
            exit();
        } else {
            $error = "密碼錯誤";
        }
    } else {
        $error = "帳號不存在";
    }

    $stmt->close();
    $conn->close();
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
    <?php include 'header.php'; ?>

    <div class="login-container">
        <h2 style="text-align: center; margin-bottom: 30px;">登入</h2>

        <form class="login-form" method="post">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <input type="text" name="username" placeholder="用戶名" required>
            <input type="password" name="password" placeholder="密碼" required>
            <button type="submit">登入</button>
        </form>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>