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
    // 嘗試建立連線
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "連線成功！"; // 測試時可以取消註解，確認後請刪除
} catch (\PDOException $e) {
    // 如果連線失敗，顯示錯誤訊息
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>