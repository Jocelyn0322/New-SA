<?php
session_start();

$id = intval($_POST['id']);

if(!isset($_SESSION['favorite'])){
    $_SESSION['favorite'] = [];
}

if(!in_array($id, $_SESSION['favorite'])){
    $_SESSION['favorite'][] = $id;
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
