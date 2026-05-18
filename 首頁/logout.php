<?php
session_start();
session_destroy();
header("Location: /NewSA/New-SA/產品/index.php");
exit();
?>