<?php
session_start();

$id = intval($_POST['id']);

if(isset($_SESSION['favorite'])){
    $_SESSION['favorite'] = array_values(
        array_diff($_SESSION['favorite'], [$id])
    );
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
