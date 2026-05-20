<?php
$host   = 'aws-1-ap-southeast-1.pooler.supabase.com';
$port   = '5432';
$dbname = 'postgres';
$user   = 'postgres.gykwxrymhgywarpyqxcr';
$pass   = '2hq5hnoEYPU2qp38';

// Supabase Storage 設定
define('SUPABASE_URL',         'https://gykwxrymhgywarpyqxcr.supabase.co');
define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5a3d4cnltaGd5d2FycHlxeGNyIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODUwMjkwNSwiZXhwIjoyMDk0MDc4OTA1fQ.A9SYbsbjQlpCpXGqMANMTlNR4ZA1Ix3NVSdznxN-PKg');
define('SUPABASE_BUCKET',      'product-images');

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=disable";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo  = new PDO($dsn, $user, $pass, $options);
    $conn = $pdo;
} catch (PDOException $e) {
    die('資料庫連線失敗：' . $e->getMessage());
}
?>
