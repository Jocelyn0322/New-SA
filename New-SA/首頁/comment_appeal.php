<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../notify_helper.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS comment_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT, video_id INT, username VARCHAR(100), content TEXT, parent_id INT NULL,
    removal_reason TEXT, appeal_reason TEXT, status VARCHAR(20) NOT NULL DEFAULT 'removed',
    admin_note TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, reviewed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$me = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appeal_id'])) {
    $aid = (int)$_POST['appeal_id'];
    $reason = trim($_POST['appeal_reason'] ?? '');
    if ($reason !== '') {
        $pdo->prepare("UPDATE comment_appeals SET appeal_reason=?, status='appealed' WHERE id=? AND username=? AND status='removed'")
            ->execute([$reason, $aid, $me]);
    }
    header('Location: comment_appeal.php');
    exit;
}

$st = $pdo->prepare("SELECT ca.*, v.title AS video_title FROM comment_appeals ca LEFT JOIN videos v ON v.id=ca.video_id WHERE ca.username=? ORDER BY ca.created_at DESC");
$st->execute([$me]);
$list = $st->fetchAll();

$statusMap = [
  'removed'  => ['❌ 已移除', '#fbeaea', '#c0392b'],
  'appealed' => ['⏳ 申訴審核中', '#fff3cd', '#856404'],
  'approved' => ['✅ 申訴通過（已恢復）', '#d4edda', '#155724'],
  'rejected' => ['🚫 申訴未通過', '#f8d7da', '#721c24'],
];
?>
<!DOCTYPE html>
<html lang="zh-Hant"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>COSMETIC — 留言申訴</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/產品/style.css">
<style>
.ca-wrap{max-width:760px;margin:32px auto 60px;padding:0 20px;}
.ca-title{font-size:22px;font-weight:800;color:#3d1520;margin-bottom:6px;}
.ca-sub{font-size:13px;color:#9b7b84;margin-bottom:22px;}
.ca-card{background:#fff;border:1px solid #f0e6ea;border-radius:14px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
.ca-badge{display:inline-block;font-size:12px;font-weight:700;padding:3px 10px;border-radius:99px;margin-bottom:8px;}
.ca-content{font-size:14px;color:#333;background:#faf8f9;border-radius:8px;padding:10px 12px;margin:8px 0;}
.ca-reason{font-size:13px;color:#9b7b84;margin:6px 0;}
.ca-form textarea{width:100%;border:1px solid #e8dde2;border-radius:8px;padding:9px 11px;font-size:13px;font-family:inherit;box-sizing:border-box;resize:vertical;margin:6px 0;}
.ca-btn{padding:9px 22px;border:none;border-radius:99px;background:linear-gradient(135deg,#6b2d3e,#c26b7c);color:#fff;font-size:14px;font-weight:700;cursor:pointer;}
.ca-empty{text-align:center;color:#b0a0b0;padding:50px 0;}
</style></head><body>
<?php include __DIR__ . '/../header.php'; ?>
<div class="ca-wrap">
  <div class="ca-title">📝 留言申訴</div>
  <div class="ca-sub">這裡列出你被管理員移除的留言。若你認為移除有誤，可以提出申訴，由管理員重新審核。</div>
  <?php if (empty($list)): ?>
    <div class="ca-empty">目前沒有被移除的留言 🎉</div>
  <?php else: foreach ($list as $r):
    [$label,$bg,$fg] = $statusMap[$r['status']] ?? ['—','#eee','#666']; ?>
    <div class="ca-card">
      <span class="ca-badge" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= $label ?></span>
      <div style="font-size:12px;color:#b0a0b0;">影片：<?= htmlspecialchars($r['video_title'] ?? '（已刪除）') ?> · <?= date('Y/m/d H:i', strtotime($r['created_at'])) ?></div>
      <div class="ca-content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
      <?php if (!empty($r['removal_reason'])): ?><div class="ca-reason">移除原因：<?= htmlspecialchars($r['removal_reason']) ?></div><?php endif; ?>
      <?php if ($r['status'] === 'removed'): ?>
        <form method="post" class="ca-form">
          <input type="hidden" name="appeal_id" value="<?= (int)$r['id'] ?>">
          <textarea name="appeal_reason" rows="3" placeholder="說明你想申訴的理由…" required></textarea>
          <button class="ca-btn" type="submit">送出申訴</button>
        </form>
      <?php elseif ($r['status'] === 'appealed'): ?>
        <div class="ca-reason">你的申訴理由：<?= htmlspecialchars($r['appeal_reason']) ?></div>
        <div style="font-size:13px;color:#856404;">管理員審核中，請耐心等候。</div>
      <?php else: ?>
        <?php if (!empty($r['appeal_reason'])): ?><div class="ca-reason">你的申訴理由：<?= htmlspecialchars($r['appeal_reason']) ?></div><?php endif; ?>
        <?php if (!empty($r['admin_note'])): ?><div class="ca-reason">管理員備註：<?= htmlspecialchars($r['admin_note']) ?></div><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
</body></html>
