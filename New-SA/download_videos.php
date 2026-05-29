<?php
/**
 * 一次性腳本：把 Supabase Storage 的影片下載並存進資料庫 video_files (LONGBLOB)
 * 並把 videos.file_path 改成串流網址 video_file.php?id=X
 * 執行：瀏覽器開 localhost/SA拷貝3/New-SA/download_videos.php
 * 執行完請刪除此檔案。
 *
 * 注意：需先在 my.cnf 調高 max_allowed_packet=64M、innodb_log_file_size=128M 並重啟 MySQL。
 */
set_time_limit(0);
ini_set('memory_limit', '1024M');
require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

// 建立 BLOB 資料表
$pdo->exec("CREATE TABLE IF NOT EXISTS video_files (
    video_id INT NOT NULL PRIMARY KEY,
    mime     VARCHAR(100) NOT NULL DEFAULT 'video/mp4',
    data     LONGBLOB     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
?>
<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="UTF-8">
<title>影片存入資料庫</title>
<style>
body{font-family:sans-serif;max-width:860px;margin:30px auto;padding:0 20px;background:#f4f3f8;}
h1{color:#c26b7c;}
.card{background:#fff;border-radius:10px;padding:10px 16px;margin-bottom:8px;box-shadow:0 1px 4px rgba(0,0,0,.06);font-size:13px;}
.ok{color:#16a34a;font-weight:700;}.err{color:#dc2626;font-weight:700;}.skip{color:#999;}
.done{background:#eafaf1;border:1px solid #a9dfbf;border-radius:12px;padding:20px;margin-top:16px;text-align:center;}
</style></head><body>
<h1>⬇️ 影片存入資料庫</h1>
<?php

$rows = $pdo->query("SELECT id, filename, file_path FROM videos ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$insertStmt = $pdo->prepare("INSERT INTO video_files (video_id, mime, data) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data)");
$updateStmt = $pdo->prepare("UPDATE videos SET file_path = ? WHERE id = ?");
$existsStmt = $pdo->prepare("SELECT 1 FROM video_files WHERE video_id = ?");

$okCount = 0; $skipCount = 0; $errCount = 0;

foreach ($rows as $row) {
    $id       = (int)$row['id'];
    $filename = $row['filename'];
    $srcUrl   = $row['file_path'];
    echo '<div class="card">#' . $id . ' ' . htmlspecialchars($filename);
    flush(); @ob_flush();

    // 已存進資料庫 → 只確保 file_path 正確
    $existsStmt->execute([$id]);
    if ($existsStmt->fetchColumn()) {
        $updateStmt->execute([BASE_URL . '/video_file.php?id=' . $id, $id]);
        echo ' <span class="skip">（已在資料庫，跳過）</span></div>';
        $skipCount++;
        continue;
    }

    // 來源網址：優先用原本 Supabase 網址下載
    $downloadUrl = $srcUrl;
    if (strpos($srcUrl, 'supabase.co') === false && !empty($filename)) {
        // 若 file_path 已被改過，仍嘗試用 Supabase 原始路徑
        $downloadUrl = SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/videos/' . rawurlencode($filename);
    }

    // 下載到暫存
    $tmp = tempnam(sys_get_temp_dir(), 'vid');
    $fh  = fopen($tmp, 'wb');
    $ch  = curl_init($downloadUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 180,
    ]);
    $success = curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);

    if ($success && $status === 200 && filesize($tmp) > 0) {
        $mime = mime_content_type($tmp) ?: 'video/mp4';
        $bin  = file_get_contents($tmp);
        @unlink($tmp);
        try {
            $insertStmt->bindValue(1, $id, PDO::PARAM_INT);
            $insertStmt->bindValue(2, $mime, PDO::PARAM_STR);
            $insertStmt->bindValue(3, $bin, PDO::PARAM_LOB);
            $insertStmt->execute();
            $updateStmt->execute([BASE_URL . '/video_file.php?id=' . $id, $id]);
            echo ' <span class="ok">✓ 已存入資料庫</span> <span class="skip">' . round(strlen($bin)/1024/1024, 1) . ' MB</span></div>';
            $okCount++;
        } catch (Exception $e) {
            echo ' <span class="err">✗ 寫入失敗：' . htmlspecialchars($e->getMessage()) . '</span></div>';
            $errCount++;
        }
    } else {
        @unlink($tmp);
        echo ' <span class="err">✗ 下載失敗 (HTTP ' . $status . ')</span></div>';
        $errCount++;
    }
    flush(); @ob_flush();
}
?>
<div class="done">
    <h2 style="color:#16a34a;">✅ 完成</h2>
    <p>成功 <strong><?= $okCount ?></strong>　跳過 <strong><?= $skipCount ?></strong>　失敗 <strong><?= $errCount ?></strong></p>
    <p style="color:#e74c3c;font-weight:700;margin-top:12px;">⚠️ 請記得刪除此檔案（download_videos.php）</p>
</div>
</body></html>
