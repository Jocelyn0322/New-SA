<?php
$host   = 'localhost';
$port   = '3306';
$dbname = 'sa_db';
$user   = 'root';
$pass   = '';

if (!defined('BASE_URL')) {
    if (getenv('RAILWAY_ENVIRONMENT') !== false) {
        define('BASE_URL', '');
    } else {
        // 動態取得專案根目錄，相容 SA / SA拷貝3 等不同資料夾名稱
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        $docRoot    = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']   ?? '');
        $relPath    = ltrim(str_replace($docRoot, '', $scriptPath), '/');
        $parts      = explode('/', $relPath);
        // 取前兩段：<專案資料夾>/New-SA
        $baseUrl    = isset($parts[1]) ? '/' . $parts[0] . '/' . $parts[1] : '/SA/New-SA';
        define('BASE_URL', $baseUrl);
    }
}


if (!defined('SUPABASE_URL'))         define('SUPABASE_URL',         'https://gykwxrymhgywarpyqxcr.supabase.co');
if (!defined('SUPABASE_SERVICE_KEY')) define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5a3d4cnltaGd5d2FycHlxeGNyIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODUwMjkwNSwiZXhwIjoyMDk0MDc4OTA1fQ.A9SYbsbjQlpCpXGqMANMTlNR4ZA1Ix3NVSdznxN-PKg');
if (!defined('SUPABASE_BUCKET'))      define('SUPABASE_BUCKET',      'product-images');

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
];

try {
    $pdo  = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES utf8mb4");
    $conn = $pdo;
} catch (PDOException $e) {
    http_response_code(503);
    die('<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="UTF-8"><title>資料庫無法連線</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f4f3f8;}
    .box{text-align:center;padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:400px;}
    h2{color:#c26b7c;margin-bottom:12px;}p{color:#666;font-size:14px;}</style></head>
    <body><div class="box"><h2>⚠️ 資料庫無法連線</h2>
    <p>請確認 XAMPP MySQL 已啟動。</p>
    <p style="font-size:12px;color:#aaa;margin-top:16px;">' . htmlspecialchars($e->getMessage()) . '</p>
    </div></body></html>');
}

date_default_timezone_set('Asia/Taipei');
?>
