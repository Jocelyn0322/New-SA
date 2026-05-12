<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<header class="topbar">
    <div class="inner">

        <a href="index.php" class="logo">AI Skin Lab</a>

        <div class="nav">
            <a href="index.php">首頁</a>
            <a href="story1.php">開始測試</a>
            <a href="../產品/index.php">產品首頁</a>
            <a href="../產品/products.php">產品</a>
            <a href="../產品/skin-match.php">膚色配對</a>
            <a href="../產品/compare.php">比較</a>
            <a href="../產品/favorite.php">收藏</a>
        </div>

        <?php if (isset($_SESSION['user']) && trim((string)$_SESSION['user']) !== ''): ?>
            <span style="font-size:14px;color:#666;">
                <a href="../首頁/profile.php" style="text-decoration:none;color:#666;margin-right:8px;"><?php echo htmlspecialchars((string)$_SESSION['user']); ?></a>
                |
                <a href="../首頁/logout.php" style="text-decoration:none;color:#666;margin-left:8px;">登出</a>
            </span>
        <?php else: ?>
            <a href="../首頁/login.php" class="login-link">登入</a>
        <?php endif; ?>

    </div>
</header>