<?php if (!defined('BASE_URL')) require_once __DIR__ . '/db.php'; ?>
<style>
.site-footer {
  background: #3d1520;
  color: rgba(255,255,255,.6);
  font-size: 13px;
  margin-top: 0;
}
.site-footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 52px 40px 44px;
  display: flex;
  align-items: flex-start;
  gap: 80px;
}
.sf-brand { flex: 0 0 200px; }
.sf-logo {
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  letter-spacing: 1px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sf-tagline {
  font-size: 13px;
  color: rgba(255,255,255,.38);
  line-height: 1.8;
}
.sf-cols {
  flex: 1;
  display: flex;
  gap: 56px;
  justify-content: flex-end;
}
.sf-col {
  display: flex;
  flex-direction: column;
  gap: 11px;
  min-width: 80px;
}
.sf-col-title {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 1.4px;
  text-transform: uppercase;
  color: rgba(255,255,255,.75);
  margin-bottom: 4px;
}
.sf-col a {
  color: rgba(255,255,255,.58);
  text-decoration: none;
  font-size: 14px;
  transition: color .15s;
  font-family: inherit;
}
.sf-col a:hover { color: #fff; }
.sf-bar {
  border-top: 1px solid rgba(255,255,255,.07);
  padding: 16px 40px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255,255,255,.28);
}
@media (max-width: 768px) {
  .site-footer-inner {
    flex-direction: column;
    gap: 36px;
    padding: 38px 22px 30px;
  }
  .sf-brand { flex: none; }
  .sf-cols { gap: 28px; justify-content: flex-start; flex-wrap: wrap; }
  .sf-bar { flex-direction: column; gap: 6px; text-align: center; padding: 14px 22px; }
}
</style>

<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="sf-brand">
      <div class="sf-logo">💄 COSMETIC</div>
      <div class="sf-tagline">找到最適合你的彩妝產品</div>
    </div>
    <div class="sf-cols">
      <div class="sf-col">
        <div class="sf-col-title">平台</div>
        <a href="<?= BASE_URL ?>/產品/index.php">首頁</a>
        <a href="<?= BASE_URL ?>/AI/index.php">AI 膚質檢測</a>
        <a href="<?= BASE_URL ?>/產品/products.php">產品資料庫</a>
        <a href="<?= BASE_URL ?>/首頁/video.php">影片交流</a>
      </div>
      <div class="sf-col">
        <div class="sf-col-title">功能</div>
        <a href="<?= BASE_URL ?>/產品/favorite.php">產品收藏</a>
        <a href="<?= BASE_URL ?>/產品/skinmatch.php">天氣-產品推薦</a>
        <a href="<?= BASE_URL ?>/產品/compare.php">產品比較</a>
        <a href="<?= BASE_URL ?>/首頁/profile.php">個人資料</a>
      </div>
      <div class="sf-col">
        <div class="sf-col-title">帳號</div>
        <a href="<?= BASE_URL ?>/首頁/login.php">登入</a>
        <a href="<?= BASE_URL ?>/首頁/login.php?mode=register">註冊</a>
        <a href="<?= BASE_URL ?>/首頁/forgot_password.php">忘記密碼</a>
      </div>
    </div>
  </div>
  <div class="sf-bar">
    <span>© <?php echo date("Y"); ?> COSMETIC ｜ 輔仁大學</span>
    <span>透過 AI 膚色分析，精準推薦適合你的彩妝</span>
  </div>
</footer>
<script src="<?= BASE_URL ?>/interactions.js"></script>
