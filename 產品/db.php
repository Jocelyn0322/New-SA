<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "project";
$port = 3307;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}
?>