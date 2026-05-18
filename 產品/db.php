<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "sa_db";

// 自動偵測 MySQL port（3306 或 3307）
$port = null;
foreach ([3306, 3307] as $try_port) {
    $test = @fsockopen($host, $try_port, $errno, $errstr, 1);
    if ($test) {
        fclose($test);
        $port = $try_port;
        break;
    }
}
if (!$port) die("找不到 MySQL 服務（已試 3306/3307）");

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