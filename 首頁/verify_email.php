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
    if (isset($_POST['verify_code'])) {
        $code = trim($_POST['verify_code']);

        $stmt = $conn->prepare("
            SELECT id, role, verification_code, verification_expiry
            FROM users
            WHERE username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRow = $result->fetch_assoc();
        $stmt->close();

        if (!$userRow) {
            $error = "找不到使用者資料";
        } elseif ($userRow['verification_code'] !== $code) {
            $error = "驗證碼錯誤";
        } elseif ($userRow['verification_expiry'] < date("Y-m-d H:i:s")) {
            $error = "驗證碼已過期，請重新產生";
        } else {
            $stmt = $conn->prepare("
                UPDATE users
                SET email_verified = 1,
                    verification_code = NULL,
                    verification_expiry = NULL
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userRow['id']);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user'] = $username;
            $_SESSION['role'] = $userRow['role'];

            unset($_SESSION['pending_user']);
            unset($_SESSION['pending_email']);

            $conn->close();

            header("Location: index.php");
            exit();
        }
    }

    if (isset($_POST['resend_code'])) {
        $newCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $stmt = $conn->prepare("
            UPDATE users
            SET verification_code = ?,
                verification_expiry = ?
            WHERE username = ?
        ");
        $stmt->bind_param("sss", $newCode, $expiry, $username);
        $stmt->execute();
        $stmt->close();

        $success = "新的驗證碼已產生";
    }
}

$stmt = $conn->prepare("
    SELECT verification_code, verification_expiry
    FROM users
    WHERE username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$userRow = $result->fetch_assoc();
$stmt->close();

$conn->close();
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

        .code-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .code-box strong {
            font-size: 26px;
            color: #d32f2f;
            letter-spacing: 3px;
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
<?php include 'header.php'; ?>

<div class="verify-container">
    <h2>Email 驗證</h2>

    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <p class="note">
        帳號：<?php echo htmlspecialchars($username); ?><br>
        Email：<?php echo htmlspecialchars($email); ?>
    </p>

    <?php if ($userRow && !empty($userRow['verification_code'])): ?>
        <div class="code-box">
            <div>你的驗證碼</div>
            <strong><?php echo htmlspecialchars($userRow['verification_code']); ?></strong>
            <div class="note">
                到期時間：<?php echo htmlspecialchars($userRow['verification_expiry']); ?>
            </div>
        </div>
    <?php endif; ?>

    <form class="verify-form" method="post">
        <input type="text" name="verify_code" placeholder="輸入驗證碼" required>
        <button type="submit">確認驗證</button>
    </form>

    <form class="verify-form" method="post">
        <button type="submit" name="resend_code" class="secondary">重新產生驗證碼</button>
    </form>
</div>

<?php include 'footer.php'; ?>
</body>
</html>