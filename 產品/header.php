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
            <a href="<?= $base ?>index.php">首頁</a>
            <a href="<?= $aiBase ?>index.php">AI</a>
            <a href="<?= $base ?>products.php">產品</a>
            <a href="<?= $base ?>skin-match.php">膚色配對</a>
            <a href="<?= $base ?>compare.php">比較</a>
            <a href="<?= $base ?>favorite.php">收藏</a>
        </div>

        <?php if (isset($_SESSION['user']) && trim((string)$_SESSION['user']) !== ''): ?>
            <div class="header-right">
                <a href="../首頁/profile.php" class="username" style="text-decoration:none;">
                    <?= htmlspecialchars((string)$_SESSION['user']) ?>
                </a>
                <a href="../首頁/logout.php" class="logout-link">登出</a>
            </div>
        <?php else: ?>
            <a href="../首頁/login.php" class="login-link">登入</a>
        <?php endif; ?>

    </div>
</header>
