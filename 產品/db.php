<?php
$user = "root";
$pass = "";
$socket = "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock";
$dbCandidates = ["sa_db", "makeupmakeup", "project"];

// Try socket first (Mac), then TCP port 3307 (friend's PC)
$connections = [];
foreach ($dbCandidates as $db) {
    $connections[] = ['host' => 'localhost', 'port' => 3306, 'socket' => $socket, 'db' => $db];
}
foreach ($dbCandidates as $db) {
    $connections[] = ['host' => '127.0.0.1', 'port' => 3307, 'socket' => '', 'db' => $db];
}

$conn = null;
$lastError = '';

foreach ($connections as $c) {
    $tryConn = $c['socket']
        ? @new mysqli($c['host'], $user, $pass, $c['db'], $c['port'], $c['socket'])
        : @new mysqli($c['host'], $user, $pass, $c['db'], $c['port']);
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
