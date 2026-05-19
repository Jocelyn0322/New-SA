<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']) || trim((string)$_SESSION['user']) === '') {
    echo json_encode(['error' => false, 'loggedIn' => false, 'history' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = trim((string)$_SESSION['user']);
require __DIR__ . '/db.php';

$existsStmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'user_product_feedback')");
$tableExists = $existsStmt && $existsStmt->fetchColumn();

if (!$tableExists) {
    echo json_encode(['error' => false, 'loggedIn' => true, 'history' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT product_id, feedback_type, detected_l, detected_a, detected_b, updated_at
     FROM user_product_feedback
     WHERE username = :username
     ORDER BY updated_at DESC
     LIMIT 30'
);
$stmt->execute([':username' => $username]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['error' => false, 'loggedIn' => true, 'history' => $rows], JSON_UNESCAPED_UNICODE);
