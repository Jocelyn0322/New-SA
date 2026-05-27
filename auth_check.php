<?php
/**
 * auth_check.php — 放在任意子目錄頁面頂部 require_once
 * 未登入自動導回大首頁 landing.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE_URL')) require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/landing.php");
    exit();
}
