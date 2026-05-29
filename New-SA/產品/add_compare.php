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
    if(count($_SESSION['compare']) >= 5){
        header("Location: products.php?compare_full=1");
        exit;
    }
    $_SESSION['compare'][] = $id;
}

header("Location: compare.php");
exit;