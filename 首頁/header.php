<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header class="topbar">
    <div class="header-right">
        <?php if (isset($_SESSION['user'])): ?>
            <span class="username"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="admin.php" class="admin-link">🛠️ 管理後台</a>
            <?php else: ?>
                <a href="profile.php" class="profile-link">👤 個人資料</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-link">登出</a>
        <?php else: ?>
            <a href="login.php" class="login-link">登入</a>
        <?php endif; ?>
    </div>
</header>
