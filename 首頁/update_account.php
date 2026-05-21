<?php
session_start();
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $body['action'] ?? '';
$username = $_SESSION['user'];

try {
    if ($action === 'update_info') {
        $newUsername = trim($body['username'] ?? '');
        $newEmail    = trim($body['email']    ?? '');
        $currentPwd  = $body['current_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || $user['password'] !== $currentPwd) {
            echo json_encode(['success' => false, 'message' => '目前密碼不正確']);
            exit;
        }

        $sets   = [];
        $params = [];

        if ($newUsername !== '' && $newUsername !== $username) {
            if (!preg_match('/^[\w\x{4e00}-\x{9fff}]{2,20}$/u', $newUsername)) {
                echo json_encode(['success' => false, 'message' => '帳號名稱只能含字母、數字、底線，2-20字元']);
                exit;
            }
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$newUsername]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => '此帳號名稱已被使用']);
                exit;
            }
            $sets[]   = 'username = ?';
            $params[] = $newUsername;
        }

        if ($newEmail !== '') {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Email 格式不正確']);
                exit;
            }
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND username != ?");
            $chk->execute([$newEmail, $username]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => '此 Email 已被使用']);
                exit;
            }
            $sets[]   = 'email = ?';
            $params[] = $newEmail;
        }

        if (empty($sets)) {
            echo json_encode(['success' => false, 'message' => '沒有任何變更']);
            exit;
        }

        $pdo->beginTransaction();

        $params[] = $username;
        $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE username = ?")
            ->execute($params);

        if ($newUsername !== '' && $newUsername !== $username) {
            foreach ([
                "UPDATE user_profiles  SET username   = ? WHERE username   = ?",
                "UPDATE videos         SET uploaded_by= ? WHERE uploaded_by= ?",
                "UPDATE video_comments SET username   = ? WHERE username   = ?",
                "UPDATE follows        SET follower   = ? WHERE follower   = ?",
                "UPDATE follows        SET following  = ? WHERE following  = ?",
                "UPDATE comment_likes  SET user_id   = ? WHERE user_id    = ?",
                "UPDATE likes          SET user_id   = ? WHERE user_id    = ?",
                "UPDATE comment_reports SET reported_by=? WHERE reported_by= ?",
            ] as $sql) {
                $pdo->prepare($sql)->execute([$newUsername, $username]);
            }
            $_SESSION['user'] = $newUsername;
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => '帳號資料已更新', 'new_username' => $_SESSION['user']]);

    } elseif ($action === 'update_password') {
        $currentPwd = $body['current_password'] ?? '';
        $newPwd     = $body['new_password']     ?? '';
        $confirmPwd = $body['confirm_password'] ?? '';

        if (strlen($newPwd) < 6) {
            echo json_encode(['success' => false, 'message' => '新密碼至少需要 6 個字元']);
            exit;
        }
        if ($newPwd !== $confirmPwd) {
            echo json_encode(['success' => false, 'message' => '新密碼與確認密碼不一致']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || $user['password'] !== $currentPwd) {
            echo json_encode(['success' => false, 'message' => '目前密碼不正確']);
            exit;
        }

        $pdo->prepare("UPDATE users SET password = ? WHERE username = ?")
            ->execute([$newPwd, $username]);

        echo json_encode(['success' => true, 'message' => '密碼已更新']);

    } else {
        echo json_encode(['success' => false, 'message' => '不支援的操作']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '資料庫錯誤']);
}
