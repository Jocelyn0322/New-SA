<?php
session_start();
session_destroy();
header("Location: /SA/New-SA/產品/index.php");
exit();
?>