<?php
session_start();
require __DIR__ . '/../db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: products.php');
    exit;
}

// 處理標記已處理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    $rid = intval($_POST['resolve_id']);
    try {
        $pdo->prepare("UPDATE product_reports SET status = 'resolved' WHERE id = ?")->execute([$rid]);
    } catch (Exception $e) {}
    header('Location: report_manage.php');
    exit;
}

// 處理刪除回報
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $rid = intval($_POST['delete_id']);
    try {
        $pdo->prepare("DELETE FROM product_reports WHERE id = ?")->execute([$rid]);
    } catch (Exception $e) {}
    header('Location: report_manage.php');
    exit;
}

// 處理刪除整個產品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    $pid = intval($_POST['delete_product_id']);
    if ($pid > 0) {
        try {
            $pdo->prepare("DELETE FROM product_colors  WHERE p_id = ?")->execute([$pid]);
        } catch (Exception $e) {}
        try {
            $pdo->prepare("DELETE FROM product_reports WHERE product_id = ?")->execute([$pid]);
        } catch (Exception $e) {}
        try {
            $pdo->prepare("DELETE FROM product_ratings WHERE product_id = ?")->execute([$pid]);
        } catch (Exception $e) {}
        try {
            $pdo->prepare("DELETE FROM data WHERE id = ?")->execute([$pid]);
        } catch (Exception $e) {}
    }
    header('Location: report_manage.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'pending';
$whereStatus  = $statusFilter === 'all' ? '' : "AND (r.status IS NULL OR r.status = 'pending')";

try {
    $reports = $pdo->query("
        SELECT r.id, r.username, r.product_id, r.report_type, r.description,
               r.status, r.created_at,
               d.name AS product_name, d.brand AS product_brand, d.category AS product_category
        FROM product_reports r
        LEFT JOIN data d ON d.id = r.product_id
        WHERE 1=1 $whereStatus
        ORDER BY r.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $reports = [];
    $dbError = $e->getMessage();
}

$typeLabel = [
    'discontinued' => '已停產',
    'new_version'  => '有新版本',
    'wrong_info'   => '資訊有誤',
    'other'        => '其他',
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=3">
    <title>問題回報管理</title>
    <style>
    .report-page { max-width: 1100px; margin: 40px auto 80px; padding: 0 40px; }
    .report-page h2 { font-size: 22px; margin-bottom: 6px; }
    .report-page .subtitle { font-size: 13px; color: #aaa; margin-bottom: 24px; }
    .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
    .filter-tabs a {
        padding: 6px 18px; border-radius: 20px; font-size: 13px;
        text-decoration: none; border: 1.5px solid #ddd; color: #666;
    }
    .filter-tabs a.active { background: #efc6cd; border-color: #efc6cd; color: #333; }
    .report-table { width: 100%; border-collapse: collapse; background: white;
        border-radius: 14px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
    .report-table th { background: #fdf5f6; font-size: 13px; color: #888;
        font-weight: 600; padding: 12px 16px; text-align: left; border-bottom: 1px solid #f0e0e3; }
    .report-table td { padding: 12px 16px; font-size: 14px; color: #333;
        border-bottom: 1px solid #f8f0f2; vertical-align: top; }
    .report-table tr:last-child td { border-bottom: none; }
    .report-table tr:hover td { background: #fffbfc; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 12px;
        font-size: 12px; font-weight: 600; }
    .badge-discontinued { background: #fde8e8; color: #c0392b; }
    .badge-new_version  { background: #e8f4fd; color: #2471a3; }
    .badge-wrong_info   { background: #fef9e7; color: #b7770d; }
    .badge-other        { background: #f2f2f2; color: #666; }
    .badge-resolved     { background: #eafaf1; color: #27ae60; }
    .action-btns { display: flex; gap: 6px; flex-wrap: wrap; }
    .action-btns a, .action-btns button {
        padding: 5px 12px; border-radius: 8px; font-size: 12px; cursor: pointer;
        text-decoration: none; border: 1.5px solid #ddd; background: white; color: #555;
    }
    .action-btns a:hover { background: #fff4f6; border-color: #efc6cd; color: #c97b8a; }
    .action-btns .btn-resolve { border-color: #a9dfbf; color: #27ae60; }
    .action-btns .btn-resolve:hover { background: #eafaf1; }
    .action-btns .btn-delete { border-color: #f5c6c6; color: #c0392b; }
    .action-btns .btn-delete:hover { background: #fde8e8; }
    .desc-text { font-size: 13px; color: #777; margin-top: 4px; }
    .empty-msg { text-align: center; padding: 60px; color: #bbb; font-size: 16px; }
    @media(max-width:768px){ .report-page{ padding: 0 16px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<div class="report-page">
    <h2>問題回報管理</h2>
    <p class="subtitle">使用者回報有問題的商品，請審查後進行編輯或標記已處理。</p>

    <div class="filter-tabs">
        <a href="?status=pending" class="<?php echo $statusFilter !== 'all' ? 'active' : ''; ?>">待處理</a>
        <a href="?status=all"     class="<?php echo $statusFilter === 'all'  ? 'active' : ''; ?>">全部</a>
    </div>

    <?php if (!empty($dbError)): ?>
        <div style="background:#fff0f0;padding:16px;border-radius:10px;color:#c0392b;font-size:13px;">
            資料庫錯誤：<?php echo htmlspecialchars($dbError); ?><br>
            請確認 Supabase 中有建立 <strong>product_reports</strong> 資料表（含 status、created_at 欄位）。
        </div>
    <?php elseif (empty($reports)): ?>
        <div class="empty-msg">目前沒有<?php echo $statusFilter !== 'all' ? '待處理的' : ''; ?>回報</div>
    <?php else: ?>
    <table class="report-table">
        <thead>
            <tr>
                <th>#</th>
                <th>商品</th>
                <th>回報類型</th>
                <th>說明</th>
                <th>回報者</th>
                <th>時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
            <tr>
                <td style="color:#bbb;font-size:12px;"><?php echo $r['id']; ?></td>
                <td>
                    <a href="product.php?id=<?php echo (int)$r['product_id']; ?>"
                       style="color:#c97b8a;font-weight:600;text-decoration:none;">
                        <?php echo htmlspecialchars($r['product_name'] ?? '（查無商品）'); ?>
                    </a>
                    <div style="font-size:12px;color:#aaa;margin-top:2px;">
                        <?php echo htmlspecialchars($r['product_brand'] ?? ''); ?>
                        <?php if($r['product_category']): ?> · <?php echo htmlspecialchars($r['product_category']); ?><?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php
                    $type = $r['report_type'];
                    $label = $typeLabel[$type] ?? $type;
                    ?>
                    <span class="badge badge-<?php echo htmlspecialchars($type); ?>"><?php echo $label; ?></span>
                    <?php if (($r['status'] ?? '') === 'resolved'): ?>
                        <br><span class="badge badge-resolved" style="margin-top:4px;">已處理</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="desc-text"><?php echo $r['description'] ? htmlspecialchars($r['description']) : '<em style="color:#ddd;">無說明</em>'; ?></div>
                </td>
                <td style="font-size:13px;color:#666;"><?php echo htmlspecialchars($r['username']); ?></td>
                <td style="font-size:12px;color:#aaa;white-space:nowrap;">
                    <?php echo $r['created_at'] ? substr($r['created_at'], 0, 16) : '—'; ?>
                </td>
                <td>
                    <div class="action-btns">
                        <a href="product.php?id=<?php echo (int)$r['product_id']; ?>">前往編輯</a>
                        <?php if (($r['status'] ?? '') !== 'resolved'): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="resolve_id" value="<?php echo $r['id']; ?>">
                            <button type="submit" class="btn-resolve">標記已處理</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="margin:0;"
                              onsubmit="return confirm('確定刪除此回報記錄？');">
                            <input type="hidden" name="delete_id" value="<?php echo $r['id']; ?>">
                            <button type="submit" class="btn-delete">刪除回報</button>
                        </form>
                        <?php if ($r['product_id']): ?>
                        <form method="POST" style="margin:0;"
                              onsubmit="return confirm('⚠️ 確定要刪除整個商品「<?php echo addslashes(htmlspecialchars($r['product_name'] ?? '')); ?>」？\n此操作無法復原，色號、評分、回報記錄都會一併刪除。');">
                            <input type="hidden" name="delete_product_id" value="<?php echo (int)$r['product_id']; ?>">
                            <button type="submit" class="btn-delete" style="background:#fff0f0;font-weight:600;">🗑 刪除整個產品</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../產品/footer.php'; ?>
</body>
</html>
