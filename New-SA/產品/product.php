<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE_URL')) require_once __DIR__ . '/../db.php';
else include __DIR__ . '/../db.php';

// 依分類決定評分屬性（與比較頁共用同一份清單）
require_once __DIR__ . '/rating_attributes.php';
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>COSMETIC — 產品詳情</title>
    <style>
    .color-upload-panel {
        display:none; position:fixed; bottom:24px; right:24px; z-index:999;
        background:#fff; border-radius:16px; padding:20px;
        box-shadow:0 8px 32px rgba(0,0,0,.18); width:280px;
        border:1px solid #f0d5dc;
    }
    .color-upload-panel.show { display:block; }
    .color-swatch {
        display:flex; flex-direction:column; align-items:center; gap:6px;
        cursor:pointer; padding:8px; border-radius:12px;
        border:2px solid transparent; transition:all .2s; position:relative;
    }
    .color-swatch:hover { background:#fff0f3; border-color:#efc6cd; }
    .color-swatch.active { border-color:#c97b8a; background:#fff0f3; }
    .color-circle-lg {
        width:40px; height:40px; border-radius:50%;
        border:2px solid rgba(0,0,0,.1); flex-shrink:0;
    }
    .color-label { font-size:11px; color:#888; text-align:center; max-width:60px; line-height:1.3; }
    /* 色號上的相機圖示已移除 */
    .colors-flex { display:flex; flex-wrap:wrap; gap:8px; }
    </style>
</head>

<body>

<?php include __DIR__ . '/../header.php'; ?>

<?php
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {

include 'footer.php';
    exit;
}

// 產地、成分從正規化的表讀（不動資料庫，純程式 JOIN）
$sql = "SELECT d.*, d.id AS p_id,
    po.name AS origin,
    (SELECT GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR '、')
       FROM product_ingredients pi JOIN ingredients i ON pi.ingredient_id = i.id
      WHERE pi.product_id = d.id) AS ingredients
  FROM data d
  LEFT JOIN product_origins po ON d.origin_id = po.id
  WHERE d.id = $id";
$result = $conn->query($sql);
if (!$result) {
    echo '<div class="products"><div class="empty-state"><h3>查詢失敗</h3><p>產品資料表可能尚未匯入，或資料庫連線名稱不正確。</p><p><a href="products.php">返回產品列表</a></p></div></div>';
    include 'footer.php';
    exit;
}

$row = $result->fetch();

if(!$row){
    echo '<div class="products"><div class="empty-state"><h3>產品不存在</h3><p><a href="products.php">返回產品列表</a></p></div></div>';
    include 'footer.php';
    exit;
}

$favorites = $_SESSION['favorite'] ?? [];
$isFav = in_array($row['p_id'], $favorites);

// 累加觀看數（同一 session 同一產品只算一次）
$viewedKey = 'viewed_product_' . $id;
if (empty($_SESSION[$viewedKey])) {
    $_SESSION[$viewedKey] = true;
    try {
        $pdo->prepare("UPDATE data SET view_count = view_count + 1 WHERE id = ?")
            ->execute([$id]);
    } catch (Exception $e) { /* view_count 欄位尚未建立時跳過 */ }
}
?>

<div class="product-detail">
    <div style="margin-bottom: 20px;">
        <a href="javascript:history.back()" style="color: var(--rose); text-decoration: none; font-size: 14px; font-weight: 500;">← 返回</a>
    </div>

    <div class="product-detail-grid">
        <div>
            <?php
            // 帶上每次上傳都會更新的 &v=，換圖後網址就會變，瀏覽器一定抓新圖（不靠快取標頭）
            $imgVer = (!empty($row['image_url']) && preg_match('/[?&]v=([A-Za-z0-9]+)/', $row['image_url'], $vm)) ? '&v=' . $vm[1] : '';
            $imgSrc = BASE_URL . '/image_file.php?type=product&id=' . $row['p_id'] . $imgVer;
            ?>
            <img id="mainProductImg"
                 src="<?php echo $imgSrc; ?>"
                 data-default="<?php echo $imgSrc; ?>"
                 alt="<?php echo htmlspecialchars($row['name']); ?>"
                 style="width:100%; max-height:420px; object-fit:contain; border-radius:15px; transition:opacity .2s; background:#f5f5f5; padding:8px;"
                 onerror="this.style.background='#f5f0f0';this.style.minHeight='300px';this.removeAttribute('src');">
            <p id="activeColorName" style="text-align:center;font-size:13px;color:var(--rose);margin-top:8px;min-height:18px;"></p>
        </div>

        <div class="product-info">
            <h1><?php echo htmlspecialchars($row['name']); ?></h1>
            
            <p><strong>品牌：</strong><?php echo htmlspecialchars($row['brand']); ?></p>
            <p><strong>分類：</strong><?php echo htmlspecialchars($row['category']); ?></p>
            <p><strong>產地：</strong><?php echo htmlspecialchars($row['origin']); ?></p>

            <h3>用途</h3>
            <div id="purposeWrap">
                <p id="purposeText" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($row['purpose'] ?? ''); ?></p>
                <?php if (mb_strlen($row['purpose'] ?? '') > 60): ?>
                <button id="purposeToggle" onclick="toggleBlock('purpose')" style="background:none;border:none;color:var(--rose);font-size:13px;cursor:pointer;padding:4px 0;font-family:inherit;">▼ 查看更多</button>
                <?php endif; ?>
            </div>

            <h3>主要成分</h3>
            <?php $ing = htmlspecialchars($row['ingredients'] ?? ''); ?>
            <div id="ingWrap">
                <p id="ingText" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                    <?php echo $ing; ?>
                </p>
                <?php if (mb_strlen($row['ingredients'] ?? '') > 60): ?>
                <button id="ingToggle" onclick="toggleBlock('ing')" style="background:none;border:none;color:var(--rose);font-size:13px;cursor:pointer;padding:4px 0;font-family:inherit;">▼ 查看更多</button>
                <?php endif; ?>
            </div>
            <script>
            function toggleBlock(key) {
                var t = document.getElementById(key + 'Text');
                var b = document.getElementById(key + 'Toggle');
                var collapsed = t.style.overflow !== 'visible';
                t.style.webkitLineClamp = collapsed ? 'unset' : '2';
                t.style.overflow = collapsed ? 'visible' : 'hidden';
                b.textContent = collapsed ? '▲ 收起' : '▼ 查看更多';
            }
            </script>

            <h3>注意事項</h3>
            <div id="precautionsWrap">
                <p id="precautionsText" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($row['precautions'] ?? ''); ?></p>
                <?php if (mb_strlen($row['precautions'] ?? '') > 60): ?>
                <button id="precautionsToggle" onclick="toggleBlock('precautions')" style="background:none;border:none;color:var(--rose);font-size:13px;cursor:pointer;padding:4px 0;font-family:inherit;">▼ 查看更多</button>
                <?php endif; ?>
            </div>

            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <!-- 管理員工具列 -->
            <div style="margin-bottom:16px; padding:14px; background:var(--rose-50); border-radius:var(--r); border:1px dashed var(--rose-200); display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                <span style="font-size:12px;color:#bbb;flex:1;">管理員工具</span>
                <button onclick="openEditProduct()" class="btn btn-outline" style="font-size:13px;padding:7px 16px;">✏️ 編輯產品資料</button>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="file" id="imgUpload" accept="image/*" style="display:none;" onchange="uploadImage(<?php echo $row['p_id']; ?>)">
                    <span class="btn btn-outline" style="font-size:13px;padding:7px 16px;">📷 更換照片</span>
                </label>
                <p id="uploadMsg" style="font-size:12px;color:#27ae60;margin:0;width:100%;display:none;"></p>
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <?php if (isset($_SESSION['user'])): ?>
                    <?php if($isFav){ ?>
                    <form action="remove_favorite.php" method="POST" style="flex: 1;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">❤️ 取消收藏</button>
                    </form>
                    <?php }else{ ?>
                    <form action="add_favorite.php" method="POST" style="flex: 1;">
                        <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">🤍 加入收藏</button>
                    </form>
                    <?php } ?>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/landing.php" class="btn btn-primary" style="flex:1;text-align:center;">🤍 登入後加入收藏</a>
                <?php endif; ?>

                <form action="add_compare.php" method="POST" style="flex: 1;">
                    <input type="hidden" name="id" value="<?php echo $row['p_id']; ?>">
                    <button type="submit" class="btn btn-outline" style="width: 100%;">⚖️ 加入比較</button>
                </form>
            </div>
        </div>
    </div>

    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <!-- 管理員：編輯產品資料浮窗 -->
    <div id="editProductPanel" class="color-upload-panel" style="width:340px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <strong style="font-size:14px;">編輯產品資料</strong>
            <button onclick="closeEditProduct()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#aaa;">✕</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
            <div>
                <label style="font-size:11px;color:#aaa;display:block;margin-bottom:3px;">品牌</label>
                <input id="ep_brand" type="text" style="width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13px;box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:11px;color:#aaa;display:block;margin-bottom:3px;">分類</label>
                <select id="ep_category" style="width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13px;box-sizing:border-box;">
                    <?php foreach(['底妝','遮瑕','眼影','眼線','睫毛膏','腮紅','修容','打亮','唇彩','護膚','護唇','防曬'] as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo ($row['category']??'')===$cat?'selected':''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label style="font-size:11px;color:#aaa;display:block;margin-bottom:3px;">產品名稱</label>
        <input id="ep_name" type="text" style="width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13px;box-sizing:border-box;margin-bottom:10px;">
        <label style="font-size:11px;color:#aaa;display:block;margin-bottom:3px;">產地</label>
        <input id="ep_origin" type="text" style="width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13px;box-sizing:border-box;margin-bottom:10px;">
        <label style="font-size:11px;color:#aaa;display:block;margin-bottom:3px;">用途</label>
        <textarea id="ep_purpose" rows="2" style="width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13px;box-sizing:border-box;resize:vertical;margin-bottom:10px;"></textarea>
        <label style="font-size:11px;color:#aaa;display:block;margin-bottom:3px;">主要成分</label>
        <textarea id="ep_ingredients" rows="2" style="width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13px;box-sizing:border-box;resize:vertical;margin-bottom:10px;"></textarea>
        <label style="font-size:11px;color:#aaa;display:block;margin-bottom:3px;">注意事項</label>
        <textarea id="ep_precautions" rows="2" style="width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13px;box-sizing:border-box;resize:vertical;margin-bottom:12px;"></textarea>
        <button onclick="saveEditProduct(<?php echo $row['p_id']; ?>)" class="btn btn-primary" style="width:100%;">儲存</button>
        <p id="editProductMsg" style="font-size:12px;margin-top:8px;min-height:16px;"></p>
    </div>
    <?php endif; ?>

    <h3 style="margin-top:40px; margin-bottom:20px;">色號列表</h3>

    <?php
    $sql2    = "SELECT * FROM product_colors WHERE p_id=$id ORDER BY color_id";
    $result2 = $conn->query($sql2);
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';

    if (!$result2) {
        echo '<p style="color:#999;">色號資料查詢失敗</p>';
    } elseif ($result2->rowCount() > 0) {
        $colors = $result2->fetchAll();
    ?>

    <div class="colors-flex">
    <?php foreach ($colors as $color): ?>
        <?php $hasImg = !empty($color['color_img']); ?>
        <div class="color-swatch <?php echo $hasImg ? 'color-has-img' : ''; ?>"
             data-color-id="<?php echo (int)$color['color_id']; ?>"
             data-color-name="<?php echo htmlspecialchars($color['color_name']); ?>"
             data-color-hex="<?php echo htmlspecialchars($color['color_hex'] ?? ''); ?>"
             data-color-img="<?php echo htmlspecialchars($color['color_img'] ?? ''); ?>"
             onclick="switchColor(this)">
            <div class="color-circle-lg" style="background:<?php echo htmlspecialchars($color['color_hex']); ?>;"></div>
            <div class="color-label"><?php echo htmlspecialchars($color['color_name']); ?></div>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if ($isAdmin): ?>
    <!-- 管理員：新增色號按鈕 -->
    <div style="margin-top:14px;">
        <button onclick="openAddColor()" class="btn btn-outline" style="font-size:13px;padding:6px 16px;">＋ 新增色號</button>
    </div>

    <!-- 管理員：色號照片上傳浮窗 -->
    <div id="colorUploadPanel" class="color-upload-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <strong style="font-size:14px;">色號操作</strong>
            <button onclick="closeColorUpload()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#aaa;">✕</button>
        </div>
        <p id="colorUploadName" style="font-size:13px;color:var(--rose);margin-bottom:10px;"></p>

        <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">色號名稱</label>
        <input type="text" id="editColorName" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm);font-size:13px;margin-bottom:10px;box-sizing:border-box;">
        <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">色票顏色</label>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <input type="color" id="editColorHex" value="#E0AC7A" style="width:48px;height:38px;border:1px solid var(--border);border-radius:var(--r-sm);cursor:pointer;padding:2px;" oninput="document.getElementById('editColorHexText').value=this.value;">
            <input type="text" id="editColorHexText" value="#E0AC7A" placeholder="#RRGGBB" maxlength="7"
                   style="flex:1;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm);font-size:13px;"
                   oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value))document.getElementById('editColorHex').value=this.value;">
        </div>
        <button onclick="updateColorHex()" class="btn btn-primary" style="width:100%;margin-bottom:10px;">儲存</button>
        <hr style="border:none;border-top:1px solid var(--border);margin-bottom:10px;">

        <p style="font-size:12px;color:#888;margin-bottom:6px;">更換照片</p>
        <input type="file" id="colorImgInput" accept="image/*" style="font-size:13px;width:100%;margin-bottom:8px;">
        <button onclick="uploadColorImg()" class="btn btn-primary" style="width:100%;margin-bottom:10px;">上傳</button>
        <hr style="border:none;border-top:1px solid var(--border);margin-bottom:10px;">
        <button onclick="deleteColor()" style="width:100%;padding:8px;background:var(--red-bg);border:1px solid var(--red-border);border-radius:var(--r-sm);color:var(--red);font-size:13px;cursor:pointer;">刪除此色號</button>
        <p id="colorUploadMsg" style="font-size:12px;margin-top:8px;min-height:16px;"></p>
    </div>

    <!-- 管理員：新增色號浮窗 -->
    <div id="addColorPanel" class="color-upload-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <strong style="font-size:14px;">新增色號</strong>
            <button onclick="closeAddColor()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#aaa;">✕</button>
        </div>
        <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">色號名稱</label>
        <input type="text" id="newColorName" placeholder="例：VANILLA" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm);font-size:13px;margin-bottom:10px;box-sizing:border-box;">
        <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">色票顏色</label>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
            <input type="color" id="newColorHex" value="#E0AC7A" style="width:48px;height:38px;border:1px solid var(--border);border-radius:var(--r-sm);cursor:pointer;padding:2px;" oninput="document.getElementById('newColorHexText').value=this.value;">
            <input type="text" id="newColorHexText" value="#E0AC7A" placeholder="#RRGGBB" maxlength="7"
                   style="flex:1;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm);font-size:13px;"
                   oninput="syncHexText(this.value)">
        </div>
        <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">照片（可選）</label>
        <input type="file" id="newColorImg" accept="image/*" style="font-size:13px;width:100%;margin-bottom:10px;">
        <button onclick="addColor()" class="btn btn-primary" style="width:100%;">新增</button>
        <p id="addColorMsg" style="font-size:12px;margin-top:8px;min-height:16px;"></p>
    </div>

    <p style="font-size:12px;color:#aaa;margin-top:8px;">📷 管理員：點色號可上傳照片或刪除</p>
    <?php endif; ?>

    <?php } else { ?>
        <p style="color:#999;">暫無色號資訊</p>
        <?php if ($isAdmin): ?>
        <div style="margin-top:10px;">
            <button onclick="openAddColor()" class="btn btn-outline" style="font-size:13px;padding:6px 16px;">＋ 新增色號</button>
        </div>
        <!-- 管理員：新增色號浮窗（無色號時也顯示） -->
        <div id="addColorPanel" class="color-upload-panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <strong style="font-size:14px;">新增色號</strong>
                <button onclick="closeAddColor()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#aaa;">✕</button>
            </div>
            <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">色號名稱</label>
            <input type="text" id="newColorName" placeholder="例：VANILLA" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm);font-size:13px;margin-bottom:10px;box-sizing:border-box;">
            <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">色票顏色</label>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                <input type="color" id="newColorHex" value="#E0AC7A" style="width:48px;height:38px;border:1px solid var(--border);border-radius:var(--r-sm);cursor:pointer;padding:2px;" oninput="document.getElementById('newColorHexText').value=this.value;">
                <input type="text" id="newColorHexText" value="#E0AC7A" placeholder="#RRGGBB" maxlength="7"
                       style="flex:1;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm);font-size:13px;"
                       oninput="syncHexText(this.value)">
                </div>
                <label style="font-size:12px;color:#888;display:block;margin-bottom:4px;">照片（可選）</label>
                <input type="file" id="newColorImg" accept="image/*" style="font-size:13px;width:100%;margin-bottom:10px;">
                <button onclick="addColor()" class="btn btn-primary" style="width:100%;">新增</button>
                <p id="addColorMsg" style="font-size:12px;margin-top:8px;min-height:16px;"></p>
            </div>
        <?php endif; ?>
    <?php } ?>

<?php
// 決定評分屬性
$category   = $row['category'] ?? '';
$attributes = $attributeMap[$category] ?? $defaultAttributes;

// 查詢各屬性平均分
$avgRatings = [];
if (!empty($attributes)) {
    $placeholders = implode(',', array_fill(0, count($attributes), '?'));
    try {
        $stmtAvg = $pdo->prepare("
            SELECT attribute, ROUND(AVG(score),1) AS avg_score, COUNT(*) AS total
            FROM product_ratings
            WHERE product_id = ? AND attribute IN ($placeholders)
            GROUP BY attribute
        ");
        $stmtAvg->execute(array_merge([$id], $attributes));
        foreach ($stmtAvg->fetchAll() as $r) {
            $avgRatings[$r['attribute']] = ['avg' => (float)$r['avg_score'], 'total' => (int)$r['total']];
        }
    } catch (Exception $e) { /* table may not exist yet */ }
}

// 查詢目前使用者的評分
$myRatings = [];
if (isset($_SESSION['user']) && !empty($attributes)) {
    $placeholders = implode(',', array_fill(0, count($attributes), '?'));
    try {
        $stmtMy = $pdo->prepare("
            SELECT attribute, score FROM product_ratings
            WHERE product_id = ? AND username = ? AND attribute IN ($placeholders)
        ");
        $stmtMy->execute(array_merge([$id, $_SESSION['user']], $attributes));
        foreach ($stmtMy->fetchAll() as $r) {
            $myRatings[$r['attribute']] = (int)$r['score'];
        }
    } catch (Exception $e) { /* table may not exist yet */ }
}
?>

<style>
.rating-section { margin-top: 40px; }
.rating-section h3 { margin-bottom: 20px; }
.rating-blocks { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media(max-width:600px){ .rating-blocks { grid-template-columns: 1fr; } }
.rating-block { background: var(--rose-50); border-radius: var(--r-lg); padding: 20px; border: 1px solid var(--border); }
.rating-block h4 { margin: 0 0 16px; color: var(--rose); font-size: 15px; }
.rating-row { display: flex; align-items: center; margin-bottom: 12px; gap: 10px; }
.rating-label { min-width: 72px; font-size: 13px; color: #555; }
.stars-display span, .stars-interactive span {
    font-size: 20px; cursor: default; color: #ddd; transition: color .15s;
}
.stars-display .filled { color: #f5a623; }
.stars-interactive span { cursor: pointer; }
.stars-interactive .filled { color: #f5a623; }
.stars-interactive .hovered { color: #f5a623; }
.rating-count { font-size: 11px; color: #aaa; margin-left: 4px; }
.rating-login-note { color: #aaa; font-size: 13px; margin-top: 8px; }
.rating-saved-msg { font-size: 12px; color: #27ae60; margin-left: 6px; opacity: 0; transition: opacity .4s; }
</style>

<div class="rating-section">
    <h3>商品評分</h3>
    <div class="rating-blocks">

        <!-- 平均評分 -->
        <div class="rating-block">
            <h4>使用者平均評價</h4>
            <?php foreach ($attributes as $attr): ?>
            <?php
                $avg   = $avgRatings[$attr]['avg']   ?? 0;
                $total = $avgRatings[$attr]['total']  ?? 0;
                $fullStars  = floor($avg);
                $halfStar   = ($avg - $fullStars >= 0.5) ? 1 : 0;
                $emptyStars = 5 - $fullStars - $halfStar;
            ?>
            <div class="rating-row">
                <span class="rating-label"><?php echo htmlspecialchars($attr); ?></span>
                <div class="stars-display">
                    <?php for($i=0;$i<$fullStars;$i++) echo '<span class="filled">★</span>'; ?>
                    <?php if($halfStar)                  echo '<span class="filled">☆</span>'; ?>
                    <?php for($i=0;$i<$emptyStars;$i++)  echo '<span>★</span>'; ?>
                </div>
                <span class="rating-count"><?php echo $total > 0 ? number_format($avg,1)." ({$total}人)" : '尚無評分'; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 使用者自評 -->
        <div class="rating-block">
            <h4>您的評價</h4>
            <?php if (isset($_SESSION['user']) && ($_SESSION['role'] ?? '') !== 'admin'): ?>
            <?php foreach ($attributes as $attr): ?>
            <?php $myScore = $myRatings[$attr] ?? 0; ?>
            <div class="rating-row">
                <span class="rating-label"><?php echo htmlspecialchars($attr); ?></span>
                <div class="stars-interactive" data-attr="<?php echo htmlspecialchars($attr); ?>" data-pid="<?php echo $id; ?>">
                    <?php for($i=1;$i<=5;$i++): ?>
                    <span data-val="<?php echo $i; ?>" class="<?php echo $i<=$myScore?'filled':''; ?>">★</span>
                    <?php endfor; ?>
                </div>
                <span class="rating-saved-msg" data-saved-attr="<?php echo htmlspecialchars($attr); ?>">已儲存</span>
            </div>
            <?php endforeach; ?>
            <?php elseif (($_SESSION['role'] ?? '') === 'admin'): ?>
            <p class="rating-login-note" style="color:#bbb;">管理員不開放評分</p>
            <?php else: ?>
            <p class="rating-login-note"><a href="../首頁/login.php" style="color:var(--rose);">登入</a> 後即可評分</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
// ── 色號切換主圖 ──────────────────────────────────────
var mainImg      = document.getElementById('mainProductImg');
var colorNameEl  = document.getElementById('activeColorName');
var activeSwatch = null;
var selectedColorId = null;

function switchColor(el) {
    var img  = el.dataset.colorImg;
    var name = el.dataset.colorName;
    selectedColorId = el.dataset.colorId;

    // 更新 active 樣式
    if (activeSwatch) activeSwatch.classList.remove('active');
    el.classList.add('active');
    activeSwatch = el;

    // 切換主圖
    if (img) {
        mainImg.style.opacity = '0';
        setTimeout(function() {
            mainImg.src = img;
            mainImg.style.opacity = '1';
        }, 150);
    }
    colorNameEl.textContent = name;

    // 管理員：開啟上傳浮窗
    var panel = document.getElementById('colorUploadPanel');
    if (panel) {
        document.getElementById('colorUploadName').textContent = '色號：' + name;
        document.getElementById('colorUploadMsg').textContent = '';
        document.getElementById('colorImgInput').value = '';
        var hex = el.dataset.colorHex || '#E0AC7A';
        if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) hex = '#E0AC7A';
        document.getElementById('editColorHex').value = hex;
        document.getElementById('editColorHexText').value = hex;
        document.getElementById('editColorName').value = name;
        panel.classList.add('show');
    }
}

async function updateColorHex() {
    if (!selectedColorId) return;
    var hex  = document.getElementById('editColorHexText').value.trim() || document.getElementById('editColorHex').value;
    var name = (document.getElementById('editColorName').value || '').trim();
    var msg  = document.getElementById('colorUploadMsg');
    if (!name) { msg.style.color='#e05'; msg.textContent='請輸入色號名稱'; return; }
    if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) { msg.style.color='#e05'; msg.textContent='顏色格式不正確'; return; }

    msg.style.color='#999'; msg.textContent='儲存中…';
    var form = new FormData();
    form.append('color_id', selectedColorId);
    form.append('color_hex', hex);
    form.append('color_name', name);

    try {
        var res  = await fetch('update_color.php', { method:'POST', body: form });
        var data = await res.json();
        if (data.success) {
            msg.style.color='#27ae60'; msg.textContent='✓ 已更新';
            if (activeSwatch) {
                activeSwatch.dataset.colorHex  = hex;
                activeSwatch.dataset.colorName = name;
                var circle = activeSwatch.querySelector('.color-circle-lg');
                if (circle) circle.style.background = hex;
                var label = activeSwatch.querySelector('.color-label');
                if (label) label.textContent = name;
            }
            document.getElementById('colorUploadName').textContent = '色號：' + name;
            if (colorNameEl) colorNameEl.textContent = name;
        } else {
            msg.style.color='#e05'; msg.textContent='失敗：' + data.message;
        }
    } catch(e) {
        msg.style.color='#e05'; msg.textContent='網路錯誤';
    }
}

function closeColorUpload() {
    var panel = document.getElementById('colorUploadPanel');
    if (panel) panel.classList.remove('show');
    if (activeSwatch) activeSwatch.classList.remove('active');
    activeSwatch = null;
    mainImg.src = mainImg.dataset.default;
    colorNameEl.textContent = '';
}

function openAddColor() {
    closeColorUpload();
    var panel = document.getElementById('addColorPanel');
    if (panel) {
        document.getElementById('addColorMsg').textContent = '';
        document.getElementById('newColorName').value = '';
        var ai = document.getElementById('newColorImg'); if (ai) ai.value = '';
        panel.classList.add('show');
    }
}

function closeAddColor() {
    var panel = document.getElementById('addColorPanel');
    if (panel) panel.classList.remove('show');
}

function syncHexText(val) {
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        document.getElementById('newColorHex').value = val;
    }
}

async function addColor() {
    var name = (document.getElementById('newColorName').value || '').trim();
    var hex  = document.getElementById('newColorHexText').value.trim() || document.getElementById('newColorHex').value;
    var msg  = document.getElementById('addColorMsg');
    if (!name) { msg.style.color='#e05'; msg.textContent='請輸入色號名稱'; return; }
    if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) { msg.style.color='#e05'; msg.textContent='顏色格式不正確'; return; }

    msg.style.color='#999'; msg.textContent='新增中…';
    var form = new FormData();
    form.append('p_id', <?php echo $id; ?>);
    form.append('color_name', name);
    form.append('color_hex', hex);
    var imgInput = document.getElementById('newColorImg');
    if (imgInput && imgInput.files && imgInput.files[0]) form.append('image', imgInput.files[0]);

    try {
        var res  = await fetch('add_color.php', { method:'POST', body: form });
        var data = await res.json();
        if (data.success) {
            msg.style.color='#27ae60'; msg.textContent='✓ 已新增';
            // 插入新色票到畫面
            var wrap = document.querySelector('.colors-flex');
            if (!wrap) { location.reload(); return; }
            var el = document.createElement('div');
            el.className = 'color-swatch' + (data.url ? ' color-has-img' : '');
            el.dataset.colorId   = data.color_id;
            el.dataset.colorName = name;
            el.dataset.colorHex  = hex;
            el.dataset.colorImg  = data.url || '';
            el.setAttribute('onclick', 'switchColor(this)');
            el.innerHTML = '<div class="color-circle-lg" style="background:'+hex+';"></div>'
                         + '<div class="color-label">'+name+'</div>';
            wrap.appendChild(el);
            closeAddColor();
        } else {
            msg.style.color='#e05'; msg.textContent='失敗：' + data.message;
        }
    } catch(e) {
        msg.style.color='#e05'; msg.textContent='網路錯誤';
    }
}

async function deleteColor() {
    if (!selectedColorId) return;
    var msg = document.getElementById('colorUploadMsg');
    if (!confirm('確定要刪除此色號？')) return;

    msg.style.color='#999'; msg.textContent='刪除中…';
    var form = new FormData();
    form.append('color_id', selectedColorId);

    try {
        var res  = await fetch('delete_color.php', { method:'POST', body: form });
        var data = await res.json();
        if (data.success) {
            // 移除色票 DOM
            if (activeSwatch) activeSwatch.remove();
            closeColorUpload();
        } else {
            msg.style.color='#e05'; msg.textContent='失敗：' + data.message;
        }
    } catch(e) {
        msg.style.color='#e05'; msg.textContent='網路錯誤';
    }
}

async function uploadColorImg() {
    var file = document.getElementById('colorImgInput').files[0];
    var msg  = document.getElementById('colorUploadMsg');
    if (!file) { msg.style.color='#e05'; msg.textContent='請選擇照片'; return; }
    if (!selectedColorId) return;

    msg.style.color='#999'; msg.textContent='上傳中…';
    var form = new FormData();
    form.append('color_id', selectedColorId);
    form.append('image', file);

    try {
        var res  = await fetch('upload_color_image.php', { method:'POST', body: form });
        var data = await res.json();
        if (data.success) {
            msg.style.color = '#27ae60';
            msg.textContent = '✓ 上傳成功';
            // 即時更新這個色號的 data-color-img 和主圖
            if (activeSwatch) {
                activeSwatch.dataset.colorImg = data.url;
                activeSwatch.classList.add('color-has-img');
                mainImg.src = data.url;
            }
        } else {
            msg.style.color = '#e05';
            msg.textContent = '失敗：' + data.message;
        }
    } catch(e) {
        msg.style.color='#e05'; msg.textContent='網路錯誤';
    }
}

// ── 星星評分 ──────────────────────────────────────────
document.querySelectorAll('.stars-interactive').forEach(function(container) {
    var stars = container.querySelectorAll('span');
    var attr  = container.dataset.attr;
    var pid   = container.dataset.pid;

    function setFilled(n) {
        stars.forEach(function(s, i) {
            s.classList.toggle('filled', i < n);
        });
    }

    stars.forEach(function(star) {
        star.addEventListener('mouseenter', function() {
            var v = parseInt(this.dataset.val);
            stars.forEach(function(s,i){ s.classList.toggle('hovered', i<v); });
        });
        star.addEventListener('mouseleave', function() {
            stars.forEach(function(s){ s.classList.remove('hovered'); });
        });
        star.addEventListener('click', function() {
            var score = parseInt(this.dataset.val);
            setFilled(score);
            fetch('rate_product.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({product_id: parseInt(pid), attribute: attr, score: score})
            })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var msgEl = container.parentNode.querySelector('[data-saved-attr]');
                    if (msgEl) {
                        msgEl.style.opacity = '1';
                        setTimeout(function(){ msgEl.style.opacity='0'; }, 1800);
                    }
                }
            });
        });
    });
});
</script>

</div>

<script>
// ── 編輯產品資料浮窗 ───────────────────────────────────────
(function() {
    var data = <?php echo json_encode([
        'name'        => $row['name']        ?? '',
        'brand'       => $row['brand']       ?? '',
        'category'    => $row['category']    ?? '',
        'origin'      => $row['origin']      ?? '',
        'purpose'     => $row['purpose']     ?? '',
        'ingredients' => $row['ingredients'] ?? '',
        'precautions' => $row['precautions'] ?? '',
    ], JSON_UNESCAPED_UNICODE); ?>;
    window._epData = data;
})();

function openEditProduct() {
    var d = window._epData;
    document.getElementById('ep_name').value        = d.name;
    document.getElementById('ep_brand').value       = d.brand;
    document.getElementById('ep_origin').value      = d.origin;
    document.getElementById('ep_purpose').value     = d.purpose;
    document.getElementById('ep_ingredients').value = d.ingredients;
    document.getElementById('ep_precautions').value = d.precautions;
    var sel = document.getElementById('ep_category');
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === d.category) { sel.selectedIndex = i; break; }
    }
    document.getElementById('editProductMsg').textContent = '';
    document.getElementById('editProductPanel').classList.add('show');
}

function closeEditProduct() {
    document.getElementById('editProductPanel').classList.remove('show');
}

async function saveEditProduct(pid) {
    var msg  = document.getElementById('editProductMsg');
    var name = document.getElementById('ep_name').value.trim();
    if (!name) { msg.style.color='#e05'; msg.textContent='名稱不能空白'; return; }

    msg.style.color='#999'; msg.textContent='儲存中…';
    var form = new FormData();
    form.append('product_id',  pid);
    form.append('name',        name);
    form.append('brand',       document.getElementById('ep_brand').value.trim());
    form.append('category',    document.getElementById('ep_category').value);
    form.append('origin',      document.getElementById('ep_origin').value.trim());
    form.append('purpose',     document.getElementById('ep_purpose').value.trim());
    form.append('ingredients', document.getElementById('ep_ingredients').value.trim());
    form.append('precautions', document.getElementById('ep_precautions').value.trim());

    try {
        var res  = await fetch('update_product.php', { method:'POST', body:form });
        var data = await res.json();
        if (data.success) {
            window._epData.name        = document.getElementById('ep_name').value.trim();
            window._epData.brand       = document.getElementById('ep_brand').value.trim();
            window._epData.category    = document.getElementById('ep_category').value;
            window._epData.origin      = document.getElementById('ep_origin').value.trim();
            window._epData.purpose     = document.getElementById('ep_purpose').value.trim();
            window._epData.ingredients = document.getElementById('ep_ingredients').value.trim();
            window._epData.precautions = document.getElementById('ep_precautions').value.trim();
            // 更新頁面上顯示的名稱
            document.querySelector('.product-info h1').textContent = window._epData.name;
            closeEditProduct();
        } else {
            msg.style.color='#e05'; msg.textContent='失敗：' + data.message;
        }
    } catch(e) {
        msg.style.color='#e05'; msg.textContent='網路錯誤';
    }
}

async function uploadImage(productId) {
    const file = document.getElementById('imgUpload').files[0];
    const msg  = document.getElementById('uploadMsg');
    if (!file) return;

    msg.style.display = 'block';
    msg.style.color='#999'; msg.textContent='上傳中…';

    const form = new FormData();
    form.append('product_id', productId);
    form.append('image', file);

    try {
        const res  = await fetch('upload_product_image.php', { method:'POST', body: form });
        const data = await res.json();
        if (data.success) {
            msg.style.color = '#27ae60';
            msg.textContent = '✓ 上傳成功';
            document.getElementById('mainProductImg').src = data.url + '?t=' + Date.now();
        } else {
            msg.style.color = '#e05';
            msg.textContent = '失敗：' + data.message;
        }
    } catch(e) {
        msg.style.color='#e05'; msg.textContent='網路錯誤';
    }
}
</script>

<?php include 'footer.php'; ?>

</body>
</html>