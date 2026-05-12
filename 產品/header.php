<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<header class="topbar">
    <div class="inner">

        <a href="/sa/New-SA/產品/index.php" class="logo">💄 COSMETIC</a>

        <div class="nav">
            <a href="/sa/New-SA/產品/index.php">首頁</a>
            <a href="/sa/New-SA/產品/products.php">產品</a>
            <a href="/sa/New-SA/AI/index.php">AI檢測</a>
            <a href="/sa/New-SA/產品/skinmatch.php">膚色配對</a>
            <a href="/sa/New-SA/產品/compare.php">比較</a>
            <a href="/sa/New-SA/產品/favorite.php">收藏</a>
            <a href="/sa/New-SA/首頁/video.php">影片交流</a>
        </div>

        <?php if (isset($_SESSION['user'])): ?>
            <span style="font-size:14px;color:#555;"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="/sa/New-SA/首頁/admin.php" class="login-link">管理後台</a>
            <?php else: ?>
                <a href="/sa/New-SA/首頁/profile.php" class="login-link">個人資料</a>
            <?php endif; ?>
            <a href="/sa/New-SA/首頁/logout.php" class="login-link">登出</a>
        <?php else: ?>
            <a href="/sa/New-SA/首頁/login.php" class="login-link">登入</a>
        <?php endif; ?>

    </div>
</header>
