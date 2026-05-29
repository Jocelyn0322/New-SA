<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/產品/index.php");
} else {
    header("Location: " . BASE_URL . "/landing.php");
}
exit();
