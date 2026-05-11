<?php
// 資料庫設定資訊
$host = 'localhost';          // 主機名稱，本機開發通常是 localhost
$db   = 'makeupmakeup';       // 這是你剛剛在 phpMyAdmin 建立的名稱
$user = 'root';               // 預設帳號通常是 root
$pass = '';                   // XAMPP 預設密碼是空的；MAMP 可能是 'root'
$charset = 'utf8mb4';         // 務必與 SQL 檔案中的編碼一致

// 設定 DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// 連線選項
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 開啟錯誤報告
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 設定讀取資料為關聯陣列
    PDO::ATTR_EMULATE_PREPARES   => false,                  // 關閉模擬預編譯，增加安全性
];

try {
    // PDO：給新 API 與預備語句使用
    $pdo = new PDO($dsn, $user, $pass, $options);

    // mysqli：相容既有頁面（例如 index.php 使用 $conn->query）
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new RuntimeException('mysqli 連線失敗：' . $conn->connect_error);
    }
    $conn->set_charset($charset);
} catch (\PDOException $e) {
    // 如果連線失敗，顯示錯誤訊息
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
} catch (\RuntimeException $e) {
    throw $e;
}
?>