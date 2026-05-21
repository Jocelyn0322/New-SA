<?php
session_start();
session_destroy();
header("Location: /SA拷貝/New-SA/產品/index.php");
exit();
?>