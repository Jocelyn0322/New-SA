<?php
session_start();
include __DIR__ . '/../db.php';

$id = intval($_POST['id']);

if (!isset($_SESSION['favorite'])) {
    $_SESSION['favorite'] = [];
}

if (!in_array($id, $_SESSION['favorite'])) {
    $_SESSION['favorite'][] = $id;
}

// 同步寫入 DB（供排名統計）
if (isset($_SESSION['user'])) {
    try {
        $pdo->prepare("
            INSERT IGNORE INTO product_favorites (username, product_id)
            VALUES (?, ?)
        ")->execute([$_SESSION['user'], $id]);
    } catch (Exception $e) { /* 表尚未建立時跳過 */ }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
