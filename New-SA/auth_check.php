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
    if (!defined('BASE_URL')) require_once __DIR__ . '/db.php';
    ?><!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>COSMETIC — 需要登入</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/產品/style.css">
<style>
.auth-gate{min-height:75vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px 24px;gap:14px;}
.auth-gate-icon{font-size:52px;}
.auth-gate h2{font-size:24px;font-weight:800;color:#3d1520;margin:0;}
.auth-gate p{font-size:15px;color:#9b7b84;margin:0;max-width:320px;line-height:1.6;}
.auth-gate-btn{display:inline-block;padding:13px 36px;border-radius:50px;background:linear-gradient(135deg,#6b2d3e,#c26b7c);color:#fff;font-size:15px;font-weight:700;text-decoration:none;transition:opacity .15s;}
.auth-gate-btn:hover{opacity:.88;}
.auth-gate-sec{font-size:13px;color:#c09aaa;text-decoration:none;}
.auth-gate-sec:hover{text-decoration:underline;}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main>
<div class="auth-gate">
  <div class="auth-gate-icon">✨</div>
  <h2>此頁面需要登入</h2>
  <p>登入後才能使用 AI 膚色檢測、收藏產品、影片交流等所有功能。</p>
  <a class="auth-gate-btn" href="<?= BASE_URL ?>/landing.php">立即登入 / 註冊</a>
  <a class="auth-gate-sec" href="<?= BASE_URL ?>/產品/products.php">以訪客身分瀏覽產品 →</a>
</div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
<?php
    exit();
}
