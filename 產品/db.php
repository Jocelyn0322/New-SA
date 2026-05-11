<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$port = 3307;

// 產品資料是匯入 makeupmakeup.sql，原本的 project 資料庫名稱會導致查不到表
$dbCandidates = ["makeupmakeup", "project"];
$conn = null;
$lastError = '';

foreach ($dbCandidates as $db) {
    $tryConn = @new mysqli($host, $user, $pass, $db, $port);
    if (!$tryConn->connect_error) {
        $tryConn->set_charset('utf8mb4');
        $conn = $tryConn;
        break;
    }
    $lastError = $tryConn->connect_error;
}

if (!$conn) {
    die('連線失敗: ' . ($lastError ?: '無法連線到資料庫'));
}
?>