<?php
session_start();
require __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$password = $body['password'] ?? '';
$reasons  = $body['reasons']  ?? [];
$username = $_SESSION['user'];

if (empty($reasons)) {
    echo json_encode(['success' => false, 'message' => '請至少勾選一個刪除原因']);
    exit;
}
if ($password === '') {
    echo json_encode(['success' => false, 'message' => '請輸入密碼確認']);
    exit;
}

$stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user || $user['password'] !== $password) {
    echo json_encode(['success' => false, 'message' => '密碼不正確']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Log deletion reason
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_deletions (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100),
        reasons TEXT,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->prepare("INSERT INTO account_deletions (username, reasons) VALUES (?, ?)")
        ->execute([$username, implode(', ', $reasons)]);

    // Delete dependent data in order
    foreach ([
        "DELETE FROM comment_likes   WHERE user_id     = ?",
        "DELETE FROM comment_reports WHERE reported_by = ?",
        "DELETE FROM video_comments  WHERE username    = ?",
        "DELETE FROM likes           WHERE user_id     = ?",
        "DELETE FROM videos          WHERE uploaded_by = ?",
        "DELETE FROM follows         WHERE follower    = ?",
        "DELETE FROM follows         WHERE following   = ?",
        "DELETE FROM user_profiles   WHERE username    = ?",
        "DELETE FROM users           WHERE username    = ?",
    ] as $sql) {
        $pdo->prepare($sql)->execute([$username]);
    }

    $pdo->commit();

    session_destroy();
    echo json_encode(['success' => true, 'redirect' => 'login.php']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '刪除失敗：' . $e->getMessage()]);
}
