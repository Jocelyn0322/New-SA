<?php
echo "PHP version: " . PHP_VERSION . "<br>";
echo "pdo_pgsql loaded: " . (extension_loaded('pdo_pgsql') ? 'YES' : 'NO') . "<br>";

$host   = 'aws-1-ap-southeast-1.pooler.supabase.com';
$port   = '5432';
$dbname = 'postgres';
$user   = 'postgres.gykwxrymhgywarpyqxcr';
$pass   = '2hq5hnoEYPU2qp38';
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=disable";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "連線成功！<br>";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' LIMIT 5");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "資料表：" . implode(', ', $tables);
} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage();
}
