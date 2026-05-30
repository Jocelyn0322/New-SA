<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => '未登入']);
    exit;
}

$username = $_SESSION['user'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$allowed = ['makeup_finish', 'makeup_style', 'skin_tone'];
$updates = [];
$params  = [];

foreach ($allowed as $field) {
    if (array_key_exists($field, $input)) {
        $updates[] = "{$field} = ?";
        $params[]  = $input[$field];
    }
}

if (empty($updates)) {
    echo json_encode(['ok' => false, 'msg' => '無更新項目']);
    exit;
}

$updates[] = "updated_at = NOW()";
$params[]  = $username;

try {
    $pdo->prepare("UPDATE user_profiles SET " . implode(', ', $updates) . " WHERE username = ?")
        ->execute($params);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
