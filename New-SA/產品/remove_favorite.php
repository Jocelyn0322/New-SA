<?php
session_start();
include __DIR__ . '/../db.php';

$id = intval($_POST['id']);

if (isset($_SESSION['favorite'])) {
    $_SESSION['favorite'] = array_values(
        array_diff($_SESSION['favorite'], [$id])
    );
}

// 同步刪除 DB 紀錄
if (isset($_SESSION['user'])) {
    try {
        $pdo->prepare("DELETE FROM product_favorites WHERE username = ? AND product_id = ?")
            ->execute([$_SESSION['user'], $id]);
    } catch (Exception $e) { /* 表尚未建立時跳過 */ }
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
