<?php
session_start();

if(!isset($_POST['id'])){
    header("Location: products.php");
    exit;
}

$id = intval($_POST['id']);

if(!isset($_SESSION['compare'])){
    $_SESSION['compare'] = [];
}

if(!in_array($id, $_SESSION['compare'])){
    $_SESSION['compare'][] = $id;
}

header("Location: compare.php");
exit;