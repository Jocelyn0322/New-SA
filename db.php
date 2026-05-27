<?php
$host   = 'aws-1-ap-southeast-1.pooler.supabase.com';
$port   = '5432';
$dbname = 'postgres';
$user   = 'postgres.gykwxrymhgywarpyqxcr';
$pass   = '2hq5hnoEYPU2qp38';

if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('RAILWAY_ENVIRONMENT') !== false ? '' : '/SA/New-SA');
}

if (!defined('SUPABASE_URL'))         define('SUPABASE_URL',         'https://gykwxrymhgywarpyqxcr.supabase.co');
if (!defined('SUPABASE_SERVICE_KEY')) define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5a3d4cnltaGd5d2FycHlxeGNyIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODUwMjkwNSwiZXhwIjoyMDk0MDc4OTA1fQ.A9SYbsbjQlpCpXGqMANMTlNR4ZA1Ix3NVSdznxN-PKg');
if (!defined('SUPABASE_BUCKET'))      define('SUPABASE_BUCKET',      'product-images');

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=disable";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo  = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET search_path TO public");
    $pdo->exec("SET timezone = 'Asia/Taipei'");
    $conn = $pdo;
} catch (PDOException $e) {
    die('資料庫連線失敗：' . $e->getMessage());
}

date_default_timezone_set('Asia/Taipei');
?>
