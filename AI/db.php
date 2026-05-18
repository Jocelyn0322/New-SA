<?php
$host    = '127.0.0.1';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

// 自動偵測 port（3306 或 3307）
$port = null;
foreach ([3306, 3307] as $try_port) {
    $test = @fsockopen($host, $try_port, $errno, $errstr, 1);
    if ($test) { fclose($test); $port = $try_port; break; }
}
if (!$port) die("找不到 MySQL 服務（已試 3306/3307）");

// 自動偵測資料庫名稱（sa_db 或 makeupmakeup）
$db = null;
foreach (['sa_db', 'makeupmakeup'] as $try_db) {
    try {
        $test_pdo = new PDO("mysql:host=$host;port=$port;dbname=$try_db;charset=$charset", $user, $pass);
        $db = $try_db;
        break;
    } catch (PDOException $e) { /* 繼續試下一個 */ }
}
if (!$db) die("找不到資料庫（已試 sa_db / makeupmakeup）");

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