<?php
session_start();
session_destroy();
header("Location: /sa/New-SA/產品/index.php");
exit();
?>