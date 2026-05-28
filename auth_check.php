<?php
/**
 * auth_check.php — 放在任意子目錄頁面頂部 require_once
 * 未登入自動導回大首頁 landing.php
 * 關閉瀏覽器/分頁後重回會自動登出（session cookie + sessionStorage 雙重機制）
 */
if (session_status() === PHP_SESSION_NONE) {
    // 確保 session cookie 在關閉瀏覽器時就消失（不持久化）
    session_set_cookie_params([
        'lifetime' => 0,        // 關閉瀏覽器即失效
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
if (!defined('BASE_URL')) require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/landing.php");
    exit();
}
