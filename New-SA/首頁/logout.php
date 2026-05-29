<?php
session_start();
session_destroy();

// 動態算 BASE_URL，不需要連資料庫
if (getenv('RAILWAY_ENVIRONMENT') !== false) {
    $base = '';
} else {
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $docRoot    = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']   ?? '');
    $relPath    = ltrim(str_replace($docRoot, '', $scriptPath), '/');
    $pos        = strpos($relPath, 'New-SA');
    $base       = $pos !== false ? '/' . rtrim(substr($relPath, 0, $pos + strlen('New-SA')), '/') : '/SA/New-SA';
}
$redirect = $base . '/landing.php';
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body>
<script>
sessionStorage.removeItem('sa_session');
localStorage.removeItem('sa_session');
localStorage.removeItem('sa_tab_count');
window.location.replace('<?= $redirect ?>');
</script>
</body></html>
