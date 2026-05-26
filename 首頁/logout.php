<?php
session_start();
session_destroy();
require __DIR__ . '/../db.php';
header("Location: " . BASE_URL . "/產品/index.php");
exit();
?>