<?php
session_start();

$id = intval($_POST['id']);

if(isset($_SESSION['favorite'])){
    $_SESSION['favorite'] = array_values(
        array_diff($_SESSION['favorite'], [$id])
    );
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'action' => 'remove', 'id' => $id]);
    exit;
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'products.php'));
exit;
