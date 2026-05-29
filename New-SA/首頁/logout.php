<?php
session_start();
session_destroy();
require __DIR__ . '/../db.php';
$redirect = BASE_URL . '/landing.php';
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
