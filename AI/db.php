<?php
$host    = '127.0.0.1';
$db      = 'sa_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

// 實際試 MySQL 連線偵測 port（3307 先試，再 3306）
$port = null;
foreach ([3307, 3306] as $try_port) {
    try {
        $test = new PDO(
            "mysql:host=$host;port=$try_port;charset=$charset",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $port = $try_port;
        $test = null;
        break;
    } catch (PDOException $e) {
        continue;
    }
}
if (!$port) die("找不到可用的 MySQL 服務（已試 3307/3306）");

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo  = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=$charset", $user, $pass, $options);
    $conn = new mysqli($host, $user, $pass, $db, $port);
    if ($conn->connect_error) {
        throw new RuntimeException('mysqli 連線失敗：' . $conn->connect_error);
    }
    $conn->set_charset($charset);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
} catch (\RuntimeException $e) {
    throw $e;
}
?>
