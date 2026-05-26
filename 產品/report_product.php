<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

require __DIR__ . '/../db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS product_reports (
    id          SERIAL PRIMARY KEY,
    username    VARCHAR(100) NOT NULL,
    product_id  INT NOT NULL,
    report_type VARCHAR(50)  NOT NULL,
    description TEXT,
    status      VARCHAR(20)  DEFAULT 'pending',
    created_at  TIMESTAMP    DEFAULT NOW()
)");

$data       = json_decode(file_get_contents('php://input'), true) ?? [];
$username   = $_SESSION['user'];
$productId  = intval($data['product_id']  ?? 0);
$reportType = trim($data['report_type']   ?? '');
$description = trim($data['description'] ?? '');

$allowedTypes = ['discontinued', 'new_version', 'wrong_info', 'other'];

if (!$productId || !in_array($reportType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => '資料不完整']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_reports (username, product_id, report_type, description)
        VALUES (:username, :product_id, :report_type, :description)
    ");
    $stmt->execute([
        ':username'    => $username,
        ':product_id'  => $productId,
        ':report_type' => $reportType,
        ':description' => $description,
    ]);
    echo json_encode(['success' => true, 'message' => '回報已送出，感謝您的協助！']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '送出失敗：' . $e->getMessage()]);
}
