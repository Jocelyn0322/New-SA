<?php
// $base    = relative path from current file back to 產品/ (e.g. '' inside 產品, '../產品/' from AI)
// $aiBase  = relative path from current file to AI/      (e.g. '../AI/' inside 產品, '' from AI)
$base   = $base   ?? '';
$aiBase = $aiBase ?? '../AI/';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<header class="topbar">
    <div class="inner">

        <a href="<?= $base ?>index.php" class="logo">💄 COSMETIC</a>

        <div class="nav">
            <a href="index.php">首頁</a>
            <a href="products.php">產品</a>
            <a href="skin-match.php">膚色配對</a>
            <a href="compare.php">比較</a>
            <a href="favorite.php">收藏</a>
        </div>

        <a href="首頁/login.php" class="login-link">登入</a>

    </div>
</header>
