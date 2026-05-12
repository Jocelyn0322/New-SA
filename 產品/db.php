<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "project";
$port = 3307;

// PDO 連接（用於新代碼如 video2.php）
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("連線失敗: " . $e->getMessage());
}

// MySQLi 連接（用於兼容舊代碼）
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}
?>