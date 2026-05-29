<?php
session_start();

if(!isset($_POST['id'])){
    header("Location: compare.php");
    exit;
}

$id = intval($_POST['id']);

if(isset($_SESSION['compare'])){
    $_SESSION['compare'] = array_values(array_diff($_SESSION['compare'], [$id]));
}

header("Location: compare.php");
exit;