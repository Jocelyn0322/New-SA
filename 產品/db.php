<?php
$host   = 'aws-1-ap-southeast-1.pooler.supabase.com';
$port   = '5432';
$dbname = 'postgres';
$user   = 'postgres.gykwxrymhgywarpyqxcr';
$pass   = '2hq5hnoEYPU2qp38'; // ← 填入你的 Supabase 密碼

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=disable";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $conn = $pdo;
} catch (PDOException $e) {
    die('資料庫連線失敗：' . $e->getMessage());
}
?>
